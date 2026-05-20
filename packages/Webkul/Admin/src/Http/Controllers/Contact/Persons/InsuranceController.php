<?php

namespace Webkul\Admin\Http\Controllers\Contact\Persons;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
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

        // Actualizar los atributos usando el repositorio de personas para disparar eventos
        $this->personRepository->update([
            'entity_type'     => 'persons',
            'ci_paciente'     => $data['ci_paciente'],
            'seguro_paciente' => $data['seguro_paciente'],
        ], $id);

        // Disparar el evento manualmente por si el repositorio no lo hace (aunque debería)
        Event::dispatch('contacts.person.update.after', $person);

        // Limpiar cache previa si existe para forzar nueva verificación
        $this->clearCache($id);

        return $this->verify($id);
    }

    /**
     * Verifica seguro sin person_id (para usar en formulario de creación).
     * Recibe ci_paciente y seguro_option_id directamente en el body.
     */
    public function verifyQuick(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ci_paciente'     => 'required|string|min:3',
            'seguro_paciente' => 'required|exists:attribute_options,id',
        ]);

        $option = DB::table('attribute_options')->where('id', $data['seguro_paciente'])->first();
        $seguroName = $option->name ?? '';

        $result = $this->insuranceService->verifyWithParams($data['ci_paciente'], $seguroName);

        return response()->json($result);
    }

    /**
     * Devuelve las opciones del atributo seguro_paciente.
     */
    public function insuranceOptions(): JsonResponse
    {
        $seguroAttr = app(\Webkul\Attribute\Repositories\AttributeRepository::class)
            ->findOneByField('code', 'seguro_paciente');

        if (! $seguroAttr) {
            return response()->json(['options' => []]);
        }

        $options = DB::table('attribute_options')
            ->where('attribute_id', $seguroAttr->id)
            ->orderBy('sort_order')
            ->get(['id', 'name']);

        return response()->json(['options' => $options]);
    }

    /**
     * Devuelve el estado de cobertura persistido en BD para un paciente.
     */
    public function insuranceStatus(int $id): JsonResponse
    {
        $person = $this->personRepository->find($id);

        if (! $person) {
            return response()->json(['message' => 'Not found'], 404);
        }

        $ciAttr     = app(\Webkul\Attribute\Repositories\AttributeRepository::class)->findOneByField('code', 'ci_paciente');
        $seguroAttr = app(\Webkul\Attribute\Repositories\AttributeRepository::class)->findOneByField('code', 'seguro_paciente');

        $ci       = $ciAttr     ? $person->getCustomAttributeValue($ciAttr)     : null;
        $seguroId = $seguroAttr ? $person->getCustomAttributeValue($seguroAttr) : null;

        $seguroLabel = null;
        if ($seguroId) {
            $option      = DB::table('attribute_options')->find($seguroId);
            $seguroLabel = $option?->name;
        }

        return response()->json([
            'ci'           => $ci,
            'seguro_id'    => $seguroId,
            'seguro_label' => $seguroLabel,
            'status'       => $person->insurance_status,
            'checked_at'   => $person->insurance_checked_at?->toISOString(),
        ]);
    }

    /**
     * Limpia la caché de verificación de un paciente.
     */
    public function clearCache(int $id): JsonResponse
    {
        $person = $this->personRepository->find($id);

        if (! $person) {
            return response()->json(['message' => 'Paciente no encontrado.'], 404);
        }

        $ci = $person->getCustomAttributeValue(app(\Webkul\Attribute\Repositories\AttributeRepository::class)->findOneByField('code', 'ci_paciente'));
        $seguroId = $person->getCustomAttributeValue(app(\Webkul\Attribute\Repositories\AttributeRepository::class)->findOneByField('code', 'seguro_paciente'));

        $cacheKey = "insurance_verify_{$id}_" . md5($seguroId . '|' . $ci);
        
        \Illuminate\Support\Facades\Cache::forget($cacheKey);

        return response()->json(['message' => 'Caché de seguro limpiada correctamente.']);
    }
}
