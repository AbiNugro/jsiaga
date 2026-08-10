<?php

namespace App\Services;

use App\Models\SensorReading;
use App\Support\LightCondition;

final class RecommendationService
{
    /** @return array{title: string, summary: string, steps: array<int, string>} */
    public function forStatus(?string $status): array
    {
        return match ($status) {
            'OFFLINE' => [
                'title' => __('ui.recommendations.status.offline.title'),
                'summary' => __('ui.recommendations.status.offline.summary'),
                'steps' => __('ui.recommendations.status.offline.steps'),
            ],
            FloodStatusService::FLOOD => [
                'title' => __('ui.recommendations.status.flood.title'),
                'summary' => __('ui.recommendations.status.flood.summary'),
                'steps' => __('ui.recommendations.status.flood.steps'),
            ],
            FloodStatusService::DANGER => [
                'title' => __('ui.recommendations.status.danger.title'),
                'summary' => __('ui.recommendations.status.danger.summary'),
                'steps' => __('ui.recommendations.status.danger.steps'),
            ],
            FloodStatusService::WARNING => [
                'title' => __('ui.recommendations.status.warning.title'),
                'summary' => __('ui.recommendations.status.warning.summary'),
                'steps' => __('ui.recommendations.status.warning.steps'),
            ],
            default => [
                'title' => __('ui.recommendations.status.safe.title'),
                'summary' => __('ui.recommendations.status.safe.summary'),
                'steps' => __('ui.recommendations.status.safe.steps'),
            ],
        };
    }

    public function forReading(?SensorReading $reading): array
    {
        $recommendation = $this->forStatus($reading?->effectiveStatus());

        if (
            $reading?->effectiveStatus() === FloodStatusService::SAFE
            && LightCondition::key($reading->light) === 'cloudy'
        ) {
            $recommendation['summary'] .= ' '.__('ui.recommendations.context.safe_cloudy.summary');
            $recommendation['steps'] = [
                ...__('ui.recommendations.context.safe_cloudy.steps'),
                ...$recommendation['steps'],
            ];
        }

        return $recommendation;
    }
}
