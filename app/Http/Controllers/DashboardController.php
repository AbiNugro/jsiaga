<?php

namespace App\Http\Controllers;

use App\Models\SensorReading;
use App\Services\RecommendationService;
use App\Support\LightCondition;
use App\Support\SensorRange;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function home(RecommendationService $recommendations): View
    {
        $latest = SensorReading::query()->latest('recorded_at')->first();
        $recent = SensorReading::query()->latest('recorded_at')->limit(20)->get()->reverse()->values();

        return view('pages.home', [
            'latest' => $latest,
            'recent' => $recent,
            'lightCondition' => LightCondition::key($latest?->light),
            'recommendation' => $recommendations->forReading($latest),
        ]);
    }

    public function history(Request $request): View
    {
        $range = in_array($request->query('range'), ['1h', '6h', '24h', '7d'], true)
            ? $request->query('range')
            : '1h';
        $query = SensorReading::query()->where('recorded_at', '>=', SensorRange::since($range));
        $chartReadings = (clone $query)->oldest('recorded_at')->limit(1000)->get();

        return view('pages.history', [
            'latest' => SensorReading::query()->latest('recorded_at')->first(),
            'range' => $range,
            'chartReadings' => $chartReadings,
            'readings' => (clone $query)->latest('recorded_at')->paginate(12)->withQueryString(),
            'summary' => [
                'highest' => $chartReadings->max('water_level'),
                'average' => $chartReadings->isEmpty() ? null : round((float) $chartReadings->avg('water_level')),
                'warning' => $chartReadings->where('status', 'WARNING')->count(),
                'danger' => $chartReadings->whereIn('status', ['DANGER', 'FLOOD'])->count(),
            ],
        ]);
    }

    public function recommendations(RecommendationService $recommendations): View
    {
        $latest = SensorReading::query()->latest('recorded_at')->first();

        return view('pages.recommendations', [
            'latest' => $latest,
            'lightCondition' => LightCondition::key($latest?->light),
            'allRecommendations' => [
                'SAFE' => $recommendations->forStatus('SAFE'),
                'WARNING' => $recommendations->forStatus('WARNING'),
                'DANGER' => $recommendations->forStatus('DANGER'),
                'FLOOD' => $recommendations->forStatus('FLOOD'),
            ],
        ]);
    }

    public function chat(): View
    {
        return view('pages.chat', [
            'latest' => SensorReading::query()->latest('recorded_at')->first(),
        ]);
    }
}
