<?php

namespace Webkul\Admin\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Webkul\Activity\Repositories\ActivityRepository;
use Webkul\Admin\Services\Insurance\Contracts\InsuranceDriverInterface;
use Webkul\Admin\Services\Insurance\Drivers\AlianzaDriver;
use Webkul\Admin\Services\Insurance\Drivers\NacionalVidaDriver;
use Webkul\Admin\Services\Insurance\Drivers\OdontokingMembershipDriver;
use Webkul\Attribute\Repositories\AttributeRepository;
use Webkul\Attribute\Repositories\AttributeValueRepository;
use Webkul\Contact\Repositories\PersonRepository;

class InsuranceService
{
    protected int $cacheTtl = 3600;

    public function __construct(
        protected PersonRepository         $personRepository,
        protected AttributeRepository      $attributeRepository,
        protected AttributeValueRepository $attributeValueRepository,
        protected ActivityRepository       $activityRepository,
    ) {}

    /**
     * Verifica el seguro del paciente delegando al driver correspondiente.
     */
    public function verify(int $personId): array
    {
        $person = $this->personRepository->find($personId);

        if (! $person) {
            return $this->indeterminate('Paciente no encontrado.');
        }

        $ciAttribute     = $this->attributeRepository->findOneByField('code', 'ci_paciente');
        $seguroAttribute = $this->attributeRepository->findOneByField('code', 'seguro_paciente');

        $ci        = $person->getCustomAttributeValue($ciAttribute);
        $seguroId  = $person->getCustomAttributeValue($seguroAttribute);
        $seguroName = $seguroId && $seguroAttribute
            ? trim($this->attributeValueRepository->getAttributeLabel($seguroId, $seguroAttribute) ?? '')
            : '';

        // Sin seguro registrado
        if (empty($seguroName) || strtolower($seguroName) === 'no tiene') {
            return [
                'status'  => 'SIN_SEGURO',
                'message' => 'El paciente no cuenta con un seguro registrado en su perfil.',
                'badge'   => 'warning',
                'success' => true,
                'data'    => null,
            ];
        }

        $driver = $this->resolveDriver($seguroName);

        if (! $driver) {
            return $this->indeterminate("Seguro '{$seguroName}' no tiene integración configurada.");
        }

        // Todos los drivers externos requieren CI
        if (empty($ci)) {
            return $this->indeterminate(
                'Por favor, completa el CI del paciente en su perfil para verificar el seguro.'
            );
        }

        $result = $driver->verify($person, (string) ($ci ?? ''));
        $result['seguro_name'] = $seguroName;
        Log::info('[InsuranceService] Verificación realizada', [
            'person_id'   => $person->id,
            'status'      => $result['status'],
            'seguro_name' => $seguroName,
        ]);
        return $result;
    }

    /**
     * Resuelve el driver correcto según el nombre del seguro.
     * El matching es case-insensitive y por contenido parcial.
     */
    protected function resolveDriver(string $seguroName): ?InsuranceDriverInterface
    {
        $name = strtolower($seguroName);

        return match (true) {
            str_contains($name, 'alianza')    => app(AlianzaDriver::class),
            str_contains($name, 'nacional')   => app(NacionalVidaDriver::class),
            str_contains($name, 'membresía')  => app(OdontokingMembershipDriver::class),
            str_contains($name, 'membresia')  => app(OdontokingMembershipDriver::class),
            str_contains($name, 'odontoking') => app(OdontokingMembershipDriver::class),
            str_contains($name, 'membresia odontoking') => app(OdontokingMembershipDriver::class),
            default                           => null,
        };
    }

    /**
     * Fuerza una nueva verificación eliminando la caché del paciente.
     */
    public function forceVerify(int $personId): array
    {
        $person = $this->personRepository->find($personId);

        if ($person) {
            $ciAttribute     = $this->attributeRepository->findOneByField('code', 'ci_paciente');
            $seguroAttribute = $this->attributeRepository->findOneByField('code', 'seguro_paciente');
            $ci              = $person->getCustomAttributeValue($ciAttribute);
            $seguroId        = $person->getCustomAttributeValue($seguroAttribute);
            $cacheKey        = "insurance_verify_{$personId}_" . md5($seguroId . '|' . $ci);
            Cache::forget($cacheKey);
        }

        return $this->verify($personId);
    }

    /**
     * Crea una nota de actividad con el resultado de la verificación.
     * La nota se vincula a la tabla person_activities (visible en perfil del paciente)
     * y a lead_activities (visible en cada cita/lead relacionado).
     */
    public function createNoteActivity(int $personId, array $result): void
    {
        if (empty($result['data'])) {
            return;
        }

        $data        = $result['data'];
        $seguroName  = $result['seguro_name'] ?? ($data['CONTRATANTE'] ?? 'N/A');
        $status      = $result['status'];

        $comment  = "**Seguro:** {$seguroName}\n";
        $comment .= "**Estado:** {$status}\n";

        if (! empty($data['NOMBRE'])) {
            $comment .= '- **Paciente:** '   . $data['NOMBRE'] . "\n";
        }

        if (! empty($data['ESTADO'])) {
            $comment .= '- **Detalle:** '    . $data['ESTADO'] . "\n";
        }

        if (isset($data['VIGENCIA HASTA'])) {
            $comment .= '- **Vigencia hasta:** ' . $data['VIGENCIA HASTA'] . "\n";
        }

        if (isset($data['COASEGURO ODONTOLOGICO'])) {
            $comment .= '- **Coaseguro:** '  . $data['COASEGURO ODONTOLOGICO']           . "\n";
            $comment .= '- **Clínica:** '    . ($data['CLINICA ODONTOLOGICA']             ?? 'N/A') . "\n";
            $comment .= '- **Cobertura:** '  . ($data['COBERTURA ADICIONAL ODONTOLOGICA'] ?? 'N/A') . "\n";
        }

        if (! empty($data['TELEFONO'])) {
            $comment .= '- **Teléfono:** '   . $data['TELEFONO'] . "\n";
        }

        if (isset($data['EDAD'])) {
            $comment .= '- **Edad:** '        . $data['EDAD'] . "\n";
        }

        if (! empty($data['CODIGO'])) {
            $comment .= '- **Código cliente:** ' . $data['CODIGO'] . "\n";
        }

        $activity = $this->activityRepository->create([
            'type'    => 'note',
            'title'   => "Verificación de Seguro: {$seguroName} — {$status}",
            'comment' => $comment,
            'is_done' => 1,
            'user_id' => auth()->guard('user')->id() ?? 1,
        ]);

        // Vincular al perfil del paciente (person_activities — es la tabla que lee el módulo de personas)
        $activity->persons()->attach($personId);

        // Vincular a cada lead/cita relacionado con el paciente
        $person = $this->personRepository->find($personId);
        if ($person && $person->leads->count() > 0) {
            $activity->leads()->attach($person->leads->pluck('id')->toArray());
        }
    }

    /**
     * Verifica el seguro usando los parámetros provistos directamente (para uso desde la API REST).
     * Equivalente a verify() pero sin leer los atributos del perfil del paciente.
     */
    public function verifyDirect(int $personId, string $ci, string $seguroName): array
    {
        $person = $this->personRepository->find($personId);

        if (! $person) {
            return $this->indeterminate('Paciente no encontrado.');
        }

        if (empty($seguroName) || strtolower($seguroName) === 'no tiene') {
            return [
                'status'  => 'SIN_SEGURO',
                'message' => 'El tipo de seguro indicado no tiene integración configurada.',
                'badge'   => 'warning',
                'success' => true,
                'data'    => null,
            ];
        }

        $driver = $this->resolveDriver($seguroName);

        if (! $driver) {
            return $this->indeterminate("Seguro '{$seguroName}' no tiene integración configurada.");
        }

        $result               = $driver->verify($person, $ci);
        $result['seguro_name'] = $seguroName;

        Log::info('[InsuranceService] Verificación directa (API)', [
            'person_id'   => $person->id,
            'ci'          => $ci,
            'seguro_name' => $seguroName,
            'status'      => $result['status'],
        ]);

        if (in_array($result['status'], ['VIGENTE', 'EN_MORA'])) {
            $this->createNoteActivity($personId, $result);
        }

        return $result;
    }

    protected function indeterminate(string $message): array
    {
        return [
            'status'  => 'INDETERMINADO',
            'message' => $message,
            'badge'   => null,
            'success' => false,
            'data'    => null,
        ];
    }
}
