<?php

namespace Tests\Feature;

use App\Models\CaregiverSchedule;
use App\Models\Incident;
use App\Models\Medication;
use App\Models\MedicationAcquisition;
use App\Models\OlderAdultMedication;
use App\Models\User;
use App\Models\VacationRequest;
use App\Services\IncidentListingService;
use App\Services\MedicationStatisticsService;
use App\Transformers\MedicationStatisticsTransformer;
use App\Transformers\ScheduleCalendarTransformer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreatesCareTestData;
use Tests\TestCase;

class SeparatedServiceResponsibilitiesTest extends TestCase
{
    use RefreshDatabase;
    use CreatesCareTestData;

    #[DataProvider('invalidFilters')]
    public function test_invalid_statistics_filter_is_rejected_before_querying(string $filter): void
    {
        DB::enableQueryLog();
        DB::flushQueryLog();
        try {
            try {
                app(MedicationStatisticsService::class)->statistics($filter);
                $this->fail('Se aceptó un filtro inválido.');
            } catch (ValidationException $exception) {
                $this->assertSame(['filter' => ['El filtro debe ser day, month o year.']], $exception->errors());
            }
            $this->assertSame([], DB::getQueryLog());
        } finally {
            DB::disableQueryLog();
        }
    }

    public static function invalidFilters(): array
    {
        return [['unknown'], ['DAY'], ["day' OR 1=1 --"]];
    }

    #[DataProvider('deniedUsers')]
    public function test_incident_authorization_runs_before_querying(string $role, bool $approved, string $message): void
    {
        $user = User::factory()->make(['role' => $role, 'is_approved' => $approved]);
        DB::enableQueryLog();
        DB::flushQueryLog();
        try {
            try {
                app(IncidentListingService::class)->forDate($user, '2026-10-23');
                $this->fail('Se permitió consultar incidentes.');
            } catch (HttpResponseException $exception) {
                $this->assertSame(403, $exception->getResponse()->getStatusCode());
                $this->assertSame(['message' => $message], json_decode($exception->getResponse()->getContent(), true));
            }
            $this->assertSame([], DB::getQueryLog());
        } finally {
            DB::disableQueryLog();
        }
    }

    public static function deniedUsers(): iterable
    {
        yield 'unknown' => ['auditor', true, 'No tienes acceso para consultar incidentes.'];
        foreach (['admin', 'familiar', 'profesional'] as $role) {
            yield 'pending-'.$role => [$role, false, 'Tu cuenta debe estar aprobada para consultar incidentes.'];
        }
    }

    #[DataProvider('caregiverRoles')]
    public function test_incident_scope_keeps_canonical_and_legacy_roles_isolated(string $role): void
    {
        $caregiver = User::factory()->create(['role' => $role, 'is_approved' => true]);
        $adult = $this->createAssignedOlderAdult($caregiver);
        $otherAdult = $this->createOlderAdult();
        $own = Incident::create([
            'older_adult_id' => $adult->id, 'title' => 'Propio', 'severity' => 'media',
            'status' => 'abierto', 'incident_date' => '2026-10-23',
        ]);
        Incident::create([
            'older_adult_id' => $otherAdult->id, 'title' => 'Ajeno', 'severity' => 'media',
            'status' => 'abierto', 'incident_date' => '2026-10-23',
        ]);
        $service = app(IncidentListingService::class);
        $this->assertSame([$own->id], $service->forDate($caregiver, '2026-10-23')->pluck('id')->all());
        $page = $service->forDate($caregiver, '2026-10-23', ['per_page' => 1]);
        $this->assertSame(1, $page->total());
        $this->assertSame([$own->id], $page->getCollection()->pluck('id')->all());
        $this->assertTrue($service->forDate($caregiver, '2026-10-24')->isEmpty());
        $unassigned = User::factory()->create(['role' => $role, 'is_approved' => true]);
        $this->assertTrue($service->forDate($unassigned, '2026-10-23')->isEmpty());
    }

    public static function caregiverRoles(): array
    {
        return [['familiar'], ['cuidador_familiar'], ['profesional'], ['cuidador_profesional']];
    }

    public function test_statistics_transformer_formats_loaded_data_without_database_queries(): void
    {
        Carbon::setTestNow('2026-10-23 12:00:00');
        $medication = (new Medication)->forceFill(['id' => 1, 'name' => 'Medicamento sintético']);
        $acquisitions = collect();
        foreach (['00:00:00', '23:59:59'] as $time) {
            $acquisitions->push((new MedicationAcquisition([
                'medication_id' => 1, 'quantity' => 2, 'acquired_at' => '2026-10-23 '.$time,
            ]))->setRelation('medication', $medication));
        }
        $inventory = (new OlderAdultMedication)->forceFill([
            'id' => 7, 'medication_id' => 1, 'older_adult_id' => null, 'quantity' => 7,
            'minimum_stock' => 2, 'is_active' => false, 'administrations_count' => 0,
        ])->setRelation('medication', $medication)->setRelation('olderAdult', null);
        $page = new LengthAwarePaginator(collect([$inventory]), 1, 25, 1);
        DB::enableQueryLog();
        DB::flushQueryLog();
        try {
            $result = app(MedicationStatisticsTransformer::class)->transform(
                $acquisitions, $page, 'day', Carbon::parse('2026-10-23'), Carbon::parse('2026-10-23'), [],
            );
            $this->assertSame('1', $result['leader']['id']);
            $this->assertSame(4, $result['leader']['unitsAcquired']);
            $this->assertSame('4 unidades adquiridas hoy', $result['leader']['acquisitionLabel']);
            $this->assertCount(8, $result['leader']['chart']);
            $this->assertSame(['label' => '00:00', 'value' => 2], $result['leader']['chart'][0]);
            $this->assertSame(['label' => '21:00', 'value' => 2], $result['leader']['chart'][7]);
            $this->assertSame(0, $result['inventory'][0]['assigned_patients']);
            $this->assertSame(0, $result['inventory'][0]['active_assignments']);
            $this->assertNull($result['inventory'][0]['older_adult_name']);
            $this->assertSame(1, $result['inventory_pagination']['total']);
            $this->assertSame([], DB::getQueryLog());
        } finally {
            DB::disableQueryLog();
            Carbon::setTestNow();
        }
    }

    public function test_calendar_transformer_keeps_independent_pages_and_fallbacks_without_querying(): void
    {
        $date = Carbon::parse('2026-10-23');
        $schedule = (new CaregiverSchedule)->forceFill([
            'id' => 1, 'user_id' => 1, 'day_of_week' => 5, 'start_time' => '08:00', 'end_time' => '16:00',
        ])->setRelation('user', null);
        $vacation = (new VacationRequest)->forceFill([
            'id' => 2, 'start_date' => '2026-10-22', 'end_date' => '2026-10-24', 'status' => 'pending',
        ])->setRelation('user', null);
        $incident = (new Incident)->forceFill([
            'id' => 3, 'incident_date' => '2026-10-23', 'incident_time' => '07:00:00', 'status' => 'abierto',
        ])->setRelation('olderAdult', null)->setRelation('reporter', null);
        $transformer = app(ScheduleCalendarTransformer::class);
        DB::enableQueryLog();
        DB::flushQueryLog();
        try {
            $shift = $transformer->shift($schedule, $date, null, 'assigned', 'Nota sintética');
            $this->assertSame('Sin adulto mayor asignado', $shift['older_adult_name']);
            $this->assertSame('08:00:00', $shift['start_time']);
            $result = $transformer->transform(collect([$shift]), collect([$vacation]), collect([$incident]), $date, $date, [
                'per_page' => 1, 'page' => 2, 'events_page' => 3,
            ]);
            $this->assertCount(0, $result['shifts']);
            $this->assertSame(2, $result['pagination']['current_page']);
            $this->assertSame(1, $result['pagination']['total']);
            $this->assertSame(3, $result['events_pagination']['current_page']);
            $this->assertSame(3, $result['events_pagination']['total']);
            $this->assertSame('shift', $result['events'][0]['type']);
            $this->assertSame('Turno: Cuidador', $result['events'][0]['title']);
            $this->assertSame("08:00:00 - 16:00:00\nNota sintética", $result['events'][0]['description']);
            $this->assertSame([], DB::getQueryLog());
        } finally {
            DB::disableQueryLog();
        }
    }
}
