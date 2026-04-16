<?php

namespace Webkul\Admin\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Webkul\Contact\Repositories\PersonRepository;
use Webkul\Attribute\Repositories\AttributeRepository;
use Webkul\Attribute\Repositories\AttributeValueRepository;

use Webkul\Activity\Repositories\ActivityRepository;

class InsuranceService
{
    /**
     * Webhook URL for insurance verification.
     */
    protected string $webhookUrl = 'https://n8n.sofopolis.com/webhook/seguros-verify';

    /**
     * Cache TTL in seconds (1 hour).
     */
    protected int $cacheTtl = 3600;

    /**
     * Timeout for external request in seconds.
     */
    protected int $timeout = 8;

    /**
     * Create a new service instance.
     */
    public function __construct(
        protected PersonRepository $personRepository,
        protected AttributeRepository $attributeRepository,
        protected AttributeValueRepository $attributeValueRepository,
        protected ActivityRepository $activityRepository
    ) {}

    /**
     * Verify insurance for a specific person.
     *
     * @param  int  $personId
     * @return array
     */
    public function verify(int $personId): array
    {
        $person = $this->personRepository->find($personId);

        if (! $person) {
            return [
                'status'  => 'INDETERMINADO',
                'message' => 'Paciente no encontrado.',
                'success' => false,
            ];
        }

        $ci = $person->getCustomAttributeValue($this->attributeRepository->findOneByField('code', 'ci_paciente'));
        $seguroId = $person->getCustomAttributeValue($this->attributeRepository->findOneByField('code', 'seguro_paciente'));

        // Generamos una clave de caché única basada en los datos actuales del paciente
        $cacheKey = "insurance_verify_{$personId}_" . md5($ci . '|' . $seguroId);

        return Cache::remember($cacheKey, $this->cacheTtl, function () use ($personId) {
            return $this->performVerification($personId);
        });
    }

    /**
     * Perform the actual verification call to n8n.
     *
     * @param  int  $personId
     * @return array
     */
    protected function performVerification(int $personId): array
    {
        $person = $this->personRepository->find($personId);

        if (! $person) {
            return [
                'status'  => 'INDETERMINADO',
                'message' => 'No pudimos encontrar al paciente en el sistema.',
                'success' => false,
            ];
        }

        $ci = $person->getCustomAttributeValue($this->attributeRepository->findOneByField('code', 'ci_paciente'));
        $seguroId = $person->getCustomAttributeValue($this->attributeRepository->findOneByField('code', 'seguro_paciente'));

        if (empty($ci) || empty($seguroId)) {
            return [
                'status'  => 'INDETERMINADO',
                'message' => 'Por favor, completa el CI y la Empresa de Seguro en el perfil para poder verificar.',
                'success' => false,
            ];
        }

        // Obtener el nombre del seguro (label del select)
        $attributeSeguro = $this->attributeRepository->findOneByField('code', 'seguro_paciente');
        $seguroName = $this->attributeValueRepository->getAttributeLabel($seguroId, $attributeSeguro);

        // Caso especial: El paciente no tiene seguro
        if (trim(strtolower($seguroName)) === 'no tiene') {
            return [
                'status'  => 'SIN_SEGURO',
                'message' => 'El paciente no cuenta con un seguro registrado en su perfil.',
                'badge'   => 'warning',
                'success' => true,
            ];
        }

        try {
            $response = Http::timeout($this->timeout)
                ->post($this->webhookUrl, [
                    'empresa_seguro'   => $seguroName,
                    'carnet_identidad' => (int) $ci,
                ]);

            if ($response->failed()) {
                return [
                    'status'  => 'INDETERMINADO',
                    'message' => 'No hay conexión con la aseguradora en este momento. Inténtalo más tarde.',
                    'success' => false,
                ];
            }

            $data = $response->json();

            if (! is_array($data)) {
                return [
                    'status'  => 'INDETERMINADO',
                    'message' => 'La aseguradora respondió con un formato que no conocemos. Por favor, contacta a soporte.',
                    'success' => false,
                ];
            }

            return $this->mapResponseToState($data);

        } catch (\Exception $e) {
            Log::error('Insurance Verification Error: ' . $e->getMessage());

            return [
                'status'  => 'INDETERMINADO',
                'message' => 'La consulta tardó demasiado. Por favor, revisa tu conexión e inténtalo de nuevo.',
                'success' => false,
            ];
        }
    }

    /**
     * Map the n8n response to one of the 4 defined states.
     *
     * @param  array  $data
     * @return array
     */
    protected function mapResponseToState(array $data): array
    {
        $success = $data['success'] ?? false;
        $results = $data['data'] ?? [];

        if (! $success || empty($results)) {
            return [
                'status'  => 'NO_REGISTRADO',
                'message' => 'El paciente no figura en la base de datos de esta aseguradora.',
                'badge'   => 'warning',
                'data'    => null,
            ];
        }

        // Tomamos el primer resultado encontrado
        $insuranceData = $results[0];
        $estado = strtoupper($insuranceData['ESTADO'] ?? '');

        if ($estado === 'VIGENTE') {
            return [
                'status'  => 'VIGENTE',
                'message' => '¡Todo en orden! El seguro está activo y el paciente puede ser atendido.',
                'badge'   => 'success',
                'data'    => $insuranceData,
            ];
        }

        return [
            'status'  => 'EN_MORA',
            'message' => 'Atención: El seguro está suspendido. El paciente podría tener pagos pendientes.',
            'badge'   => 'danger',
            'data'    => $insuranceData,
        ];
    }

    /**
     * Crea una nota de actividad con los detalles del seguro.
     */
    public function createNoteActivity(int $personId, array $result): void
    {
        if (!isset($result['data']) || empty($result['data'])) return;

        $data = $result['data'];
        $comment = "### Verificación de Seguro: {$result['status']}\n";
        $comment .= "- **Aseguradora:** " . ($data['CONTRATANTE'] ?? 'N/A') . "\n";
        $comment .= "- **Estado:** " . ($data['ESTADO'] ?? 'N/A') . "\n";
        $comment .= "- **Coaseguro:** " . ($data['COASEGURO ODONTOLOGICO'] ?? 'N/A') . "\n";
        $comment .= "- **Clínica:** " . ($data['CLINICA ODONTOLOGICA'] ?? 'N/A') . "\n";
        $comment .= "- **Cobertura:** " . ($data['COBERTURA ADICIONAL ODONTOLOGICA'] ?? 'N/A') . "\n";
        
        $activity = $this->activityRepository->create([
            'type'          => 'note',
            'title'         => 'Verificación de Seguro: ' . $result['status'],
            'comment'       => $comment,
            'is_done'       => 1,
            'user_id'       => auth()->guard('user')->id() ?? 1,
            'participants'  => [
                'persons' => [$personId],
            ],
        ]);

        // Vincular con los leads del paciente si existen
        $person = $this->personRepository->find($personId);
        if ($person && $person->leads->count() > 0) {
            $activity->leads()->attach($person->leads->pluck('id')->toArray());
        }
    }
}
