<?php

namespace Webkul\Admin\Services\Insurance\Drivers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Webkul\Admin\Services\Insurance\Contracts\InsuranceDriverInterface;
use Webkul\Attribute\Repositories\AttributeRepository;
use Webkul\Attribute\Repositories\AttributeValueRepository;

/**
 * Driver para Membresía Odontoking.
 *
 * Verifica via webhook n8n (SEGUROS_WEBHOOK_URL) usando empresa_seguro = "Membresía Odontoking".
 * Además, lee el atributo `estado_seguro_paciente` de la BD como contexto adicional.
 */
class OdontokingMembershipDriver implements InsuranceDriverInterface
{
    protected string $webhookUrl;
    protected int    $timeout = 8;

    public function __construct(
        protected AttributeRepository      $attributeRepository,
        protected AttributeValueRepository $attributeValueRepository,
    ) {
        $this->webhookUrl = env('SEGUROS_WEBHOOK_URL', 'https://n8n.sofopolis.com/webhook/seguros-verify');
    }

    public function verify(object $person, string $ci): array
    {
        try {
            $response = Http::timeout($this->timeout)
                ->post($this->webhookUrl, [
                    'empresa_seguro'   => 'Membresía Odontoking',
                    'carnet_identidad' => (int) $ci,
                ]);

            if ($response->failed()) {
                return [
                    'status'  => 'INDETERMINADO',
                    'message' => 'No hay conexión con la aseguradora en este momento. Inténtalo más tarde.',
                    'badge'   => null,
                    'success' => false,
                    'data'    => null,
                ];
            }

            $data = $response->json();

            if (! is_array($data)) {
                return [
                    'status'  => 'INDETERMINADO',
                    'message' => 'La aseguradora respondió con un formato que no conocemos. Por favor, contacta a soporte.',
                    'badge'   => null,
                    'success' => false,
                    'data'    => null,
                ];
            }

            return $this->mapResponse($data);
        } catch (\Exception $e) {
            Log::error('[OdontokingMembershipDriver] Error: ' . $e->getMessage());
            return [
                'status'  => 'INDETERMINADO',
                'message' => 'La consulta tardó demasiado. Por favor, revisa tu conexión e inténtalo de nuevo.',
                'badge'   => null,
                'success' => false,
                'data'    => null,
            ];
        }
    }

    protected function mapResponse(array $data): array
    {
        $success = $data['success'] ?? false;
        $results = $data['data']    ?? [];

        if (! $success || empty($results) || (is_string($results) && trim(strtolower($results)) === 'no registrado')) {
            return [
                'status'  => 'NO_REGISTRADO',
                'message' => 'El paciente no figura en la base de datos de esta aseguradora.',
                'badge'   => 'warning',
                'success' => true,
                'data'    => null,
            ];
        }

        $insuranceData = is_array($results) ? ($results[0] ?? null) : $results;

        if (! $insuranceData) {
            return [
                'status'  => 'NO_REGISTRADO',
                'message' => 'El paciente no figura en la base de datos de esta aseguradora.',
                'badge'   => 'warning',
                'success' => true,
                'data'    => null,
            ];
        }

        [$estado, $vigenciaHasta] = $this->resolveEstado($insuranceData);

        $displayData = [
            'CI'          => $insuranceData['CI']           ?? '',
            'NOMBRE'      => $insuranceData['Nombre']       ?? ($insuranceData['Paciente_Original'] ?? ''),
            'CONTRATANTE' => 'Membresía Odontoking',
            'ESTADO'      => $estado,
            'TELEFONO'    => $insuranceData['Telefono']     ?? '',
            'OBSERVACIONES' => $insuranceData['Observaciones'] ?? '',
        ];

        if ($vigenciaHasta) {
            $displayData['VIGENCIA HASTA'] = $vigenciaHasta->format('d/m/Y');
        }

        if ($estado === 'VIGENTE') {
            return [
                'status'  => 'VIGENTE',
                'message' => '¡Membresía activa! El paciente puede ser atendido con los beneficios Odontoking.',
                'badge'   => 'success',
                'success' => true,
                'data'    => $displayData,
            ];
        }

        if ($estado === 'VENCIDO') {
            return [
                'status'  => 'VENCIDO',
                'message' => 'La membresía Odontoking está vencida.' . ($vigenciaHasta ? ' Venció el ' . $vigenciaHasta->format('d/m/Y') . '.' : ''),
                'badge'   => 'danger',
                'success' => true,
                'data'    => $displayData,
            ];
        }

        return [
            'status'  => 'EN_MORA',
            'message' => 'Atención: La membresía Odontoking está suspendida. El paciente podría tener pagos pendientes.',
            'badge'   => 'danger',
            'success' => true,
            'data'    => $displayData,
        ];
    }

    /**
     * Extrae el estado y la fecha de vigencia desde el campo Observaciones.
     * Soporta: "MF HASTA EL DD/MM/YYYY" dentro del texto libre.
     * Retorna [$estado, ?\Carbon\Carbon $vigenciaHasta]
     */
    protected function resolveEstado(array $row): array
    {
        $observaciones = $row['Observaciones'] ?? '';

        // Buscar patrón "MF HASTA EL DD/MM/YYYY" (Membresía Fija)
        if (preg_match('/MF HASTA EL\s+(\d{2}\/\d{2}\/\d{4})/i', $observaciones, $m)) {
            $fecha = \Carbon\Carbon::createFromFormat('d/m/Y', $m[1])->startOfDay();
            $estado = $fecha->isFuture() ? 'VIGENTE' : 'VENCIDO';
            return [$estado, $fecha];
        }

        // Si hay un campo ESTADO explícito (futuras versiones del webhook)
        $estadoExplicito = strtoupper($row['ESTADO'] ?? '');
        if ($estadoExplicito) {
            return [$estadoExplicito, null];
        }

        return ['EN_MORA', null];
    }
}
