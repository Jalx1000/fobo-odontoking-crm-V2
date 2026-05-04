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
        Log::info('[InsuranceService] Verificación realizada', [
            'person_id' => $person->id,
            'status'    => $result['status'],
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
     */
    public function createNoteActivity(int $personId, array $result): void
    {
        if (empty($result['data'])) {
            return;
        }

        $data    = $result['data'];
        $comment = "Verificación de Seguro: {$result['status']}\n";
        $comment .= '- **Aseguradora:** ' . ($data['CONTRATANTE'] ?? 'N/A') . "\n";
        $comment .= '- **Estado:** '      . ($data['ESTADO']      ?? 'N/A') . "\n";

        if (isset($data['COASEGURO ODONTOLOGICO'])) {
            $comment .= '- **Coaseguro:** '  . $data['COASEGURO ODONTOLOGICO']           . "\n";
            $comment .= '- **Clínica:** '    . ($data['CLINICA ODONTOLOGICA']             ?? 'N/A') . "\n";
            $comment .= '- **Cobertura:** '  . ($data['COBERTURA ADICIONAL ODONTOLOGICA'] ?? 'N/A') . "\n";
        }

        if (isset($data['EDAD'])) {
            $comment .= '- **Edad:** ' . $data['EDAD'] . "\n";
        }

        if (isset($data['CODIGO'])) {
            $comment .= '- **Código cliente:** ' . $data['CODIGO'] . "\n";
        }

        $activity = $this->activityRepository->create([
            'type'         => 'note',
            'title'        => 'Verificación de Seguro: ' . $result['status'],
            'comment'      => $comment,
            'is_done'      => 1,
            'user_id'      => auth()->guard('user')->id() ?? 1,
            'participants' => ['persons' => [$personId]],
        ]);

        $person = $this->personRepository->find($personId);
        if ($person && $person->leads->count() > 0) {
            $activity->leads()->attach($person->leads->pluck('id')->toArray());
        }
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
