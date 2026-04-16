<?php

namespace Webkul\Admin\Http\Controllers\Contact\Persons;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Admin\Services\InsuranceService;
use Webkul\Contact\Repositories\PersonRepository;

class InsuranceController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct(
        protected InsuranceService $insuranceService,
        protected PersonRepository $personRepository
    ) {}

    /**
     * Verify insurance for a specific person.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function verify(int $id): JsonResponse
    {
        $person = $this->personRepository->find($id);

        if (! $person) {
            return response()->json([
                'message' => 'No encontramos al paciente.'
            ], 404);
        }

        // Obtener atributos necesarios
        $ciAttr = app(\Webkul\Attribute\Repositories\AttributeRepository::class)->findOneByField('code', 'ci_paciente');
        $seguroAttr = app(\Webkul\Attribute\Repositories\AttributeRepository::class)->findOneByField('code', 'seguro_paciente');

        $ci = $person->getCustomAttributeValue($ciAttr);
        $seguro = $person->getCustomAttributeValue($seguroAttr);

        if (empty($ci) || empty($seguro)) {
            // Si faltan campos, devolvemos las opciones del seguro para el modal
            $options = [];
            if ($seguroAttr) {
                $options = DB::table('attribute_options')
                    ->where('attribute_id', $seguroAttr->id)
                    ->get(['id', 'name']);
            }

            return response()->json([
                'message' => 'Faltan datos importantes para la validación.',
                'status'  => 'CAMPOS_VACIOS',
                'options' => $options
            ], 422);
        }

        $result = $this->insuranceService->verify($id);

        // Si se obtuvo un resultado (Vigente o Mora), creamos la nota automática
        if (in_array($result['status'], ['VIGENTE', 'EN_MORA'])) {
            $this->insuranceService->createNoteActivity($id, $result);
        }

        return response()->json($result);
    }

    /**
     * Actualiza los atributos de seguro del paciente antes de verificar.
     */
    public function updateAndVerify(int $id): JsonResponse
    {
        $data = request()->validate([
            'ci_paciente'     => 'required|string',
            'seguro_paciente' => 'required|exists:attribute_options,id',
        ]);

        $person = $this->personRepository->find($id);

        if (! $person) {
            return response()->json(['message' => 'Paciente no encontrado.'], 404);
        }

        // Guardar los atributos usando el repositorio
        app(\Webkul\Attribute\Repositories\AttributeValueRepository::class)->save([
            'entity_id'   => $id,
            'entity_type' => 'persons',
            'ci_paciente' => $data['ci_paciente'],
            'seguro_paciente' => $data['seguro_paciente'],
        ]);

        // Limpiar cache previa si existe para forzar nueva verificación
        \Illuminate\Support\Facades\Cache::forget("insurance_verify_{$id}");

        return $this->verify($id);
    }
}
