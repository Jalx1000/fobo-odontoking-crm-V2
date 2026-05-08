<?php

namespace Webkul\Admin\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Admin\Services\InsuranceService;

/**
 * @OA\SecurityScheme(
      securityScheme="bearerAuth",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="JWT",
 *     description="Ingresa el token de Sanctum: Bearer {token}"
 * )
 *
 * @OA\Tag(
 *     name="Insurance",
 *     description="Endpoints para verificación de seguros dentales"
 * )
 */
class InsuranceController extends Controller
{
    public function __construct(
        protected InsuranceService $insuranceService,
    ) {}

    /**
     * @OA\Post(
     *     path="/api/insurance/verify",
     *     summary="Verificar cobertura de seguro de un paciente",
     *     description="Realiza la verificación del seguro dental de un paciente usando el CI y el tipo de seguro proporcionados. Equivalente a la verificación interna del CRM. Si el resultado es VIGENTE o EN_MORA, crea automáticamente una nota de actividad en el perfil del paciente y sus leads relacionados.",
     *     tags={"Insurance"},
     *     security={{"bearerAuth": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 required={"person_id", "ci", "insurance_type"},
     *                 @OA\Property(
     *                     property="person_id",
     *                     type="integer",
     *                     description="ID del paciente (person) en el CRM",
     *                     example=42
     *                 ),
     *                 @OA\Property(
     *                     property="ci",
     *                     type="string",
     *                     description="Cédula de identidad del paciente",
     *                     example="12345678"
     *                 ),
     *                 @OA\Property(
     *                     property="insurance_type",
     *                     type="string",
     *                     description="Nombre del tipo de seguro. Valores soportados: 'Alianza', 'Nacional Seguro', 'Membresía Odontoking'",
     *                     example="Membresía Odontoking",
     *                     enum={"Alianza", "Nacional Seguro", "Membresía Odontoking"}
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Verificación completada. El campo 'status' indica el resultado.",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", description="Resultado de la verificación",
     *                 enum={"VIGENTE", "EN_MORA", "VENCIDO", "NO_REGISTRADO", "SIN_SEGURO", "INDETERMINADO"},
     *                 example="VIGENTE"
     *             ),
     *             @OA\Property(property="message", type="string", example="¡Membresía activa! El paciente puede ser atendido con los beneficios Odontoking."),
     *             @OA\Property(property="badge", type="string", nullable=true, enum={"success", "warning", "danger", null}, example="success"),
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="seguro_name", type="string", example="Membresía Odontoking"),
     *             @OA\Property(
     *                 property="data",
     *                 nullable=true,
     *                 type="object",
     *                 description="Datos devueltos por la aseguradora. Los campos varían según el tipo de seguro.",
     *                 example={"CI": "12345678", "NOMBRE": "Juan Pérez", "CONTRATANTE": "Membresía Odontoking", "ESTADO": "VIGENTE", "VIGENCIA HASTA": "31/12/2025"}
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="No autenticado. Se requiere Bearer token válido.",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Paciente no encontrado",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Paciente no encontrado.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error de validación en los campos enviados",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="The person_id field is required."),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     )
     * )
     */
    public function verify(): JsonResponse
    {
        $data = request()->validate([
            'person_id'      => ['required', 'integer', 'exists:persons,id'],
            'ci'             => ['required', 'string', 'max:20'],
            'insurance_type' => ['required', 'string', 'max:100'],
        ]);

        $result = $this->insuranceService->verifyDirect(
            (int) $data['person_id'],
            $data['ci'],
            $data['insurance_type'],
        );

        $httpStatus = $result['success'] === false && $result['status'] === 'INDETERMINADO' ? 502 : 200;

        return response()->json($result, $httpStatus);
    }
}
