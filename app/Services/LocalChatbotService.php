<?php

namespace App\Services;

use App\Models\SensorReading;
use App\Support\LightCondition;
use Illuminate\Support\Str;

final class LocalChatbotService
{
    public function __construct(private readonly RecommendationService $recommendations) {}

    public function answer(string $message, ?SensorReading $latest): ?string
    {
        $question = Str::lower(trim($message));

        if (preg_match('/^(hi|hai|halo|hello|안녕|안녕하세요)[!.?]*$/u', $question)) {
            return __('ui.chat.answers.greeting');
        }

        if ($this->contains($question, ['bantuan', 'help', 'bisa apa', 'contoh pertanyaan', '도움', '무엇을 할 수'])) {
            return __('ui.chat.answers.help');
        }

        if ($this->contains($question, ['batas warning', 'batas danger', 'batas bahaya', 'aturan status', 'threshold', 'warning limit', 'danger limit', 'status rules', '기준', '위험 기준'])) {
            return __('ui.chat.answers.thresholds');
        }

        $sensorQuestion = $this->contains($question, [
            'status', 'data sensor', 'sensor data', '센서 데이터', 'jarak', 'distance', '거리',
            'level air', 'water level', '수위', 'suhu', 'temperature', '온도', 'kelembapan',
            'humidity', '습도', 'cahaya', 'light', '조도', 'ldr', 'tindakan', 'action', '조치',
            'harus dilakukan', 'should i do', '해야', 'rekomendasi', 'recommendation', '권장',
            'pembaruan terakhir', 'last update', '마지막 업데이트',
        ]);

        if ($sensorQuestion && ! $latest) {
            return __('ui.chat.answers.no_data');
        }

        if (! $latest) {
            return null;
        }

        $effectiveStatus = $latest->effectiveStatus();
        $statusLabel = $effectiveStatus.' ('.match ($effectiveStatus) {
            'OFFLINE' => __('ui.status.offline'),
            FloodStatusService::FLOOD => __('ui.status.flood'),
            FloodStatusService::DANGER => __('ui.status.danger'),
            FloodStatusService::WARNING => __('ui.status.warning'),
            default => __('ui.status.safe'),
        }.')';
        $updated = $latest->recorded_at?->timezone('Asia/Jakarta')->translatedFormat('d M Y, H:i:s').' WIB';
        $unavailable = __('ui.common.unavailable');

        if ($this->contains($question, ['data sensor', 'sensor data', '센서 데이터', 'ringkasan', 'summary', '요약', 'semua data', 'all data', '전체 데이터'])) {
            return __('ui.chat.answers.summary', [
                'status' => $statusLabel,
                'level' => $this->value($latest->water_level, '%'),
                'temperature' => $this->value($latest->temperature, ' °C'),
                'humidity' => $this->value($latest->humidity, '%'),
                'light' => $this->lightCondition($latest->light),
                'updated' => $updated ?: $unavailable,
            ]);
        }

        if ($this->contains($question, ['apa yang harus', 'harus dilakukan', 'tindakan', 'rekomendasi', 'evakuasi', 'what should', 'action', 'recommendation', 'evacuation', '무엇을 해야', '조치', '권장', '대피'])) {
            return __('ui.chat.answers.action', ['status' => $statusLabel, 'recommendation' => $this->recommendations->forReading($latest)['summary']]);
        }

        if ($this->contains($question, ['level air', 'water level', 'ketinggian air', 'persen air', '수위'])) {
            return __('ui.chat.answers.level', ['value' => $this->value($latest->water_level, '%')]);
        }

        if ($this->contains($question, ['jarak air', 'jarak', 'distance', '거리'])) {
            return __('ui.chat.answers.distance', ['value' => $this->value($latest->water_level, '%')]);
        }

        if ($this->contains($question, ['suhu', 'temperature', 'temperatur', '온도'])) {
            return __('ui.chat.answers.temperature', ['value' => $this->value($latest->temperature, ' °C')]);
        }

        if ($this->contains($question, ['kelembapan', 'lembap', 'humidity', '습도'])) {
            return __('ui.chat.answers.humidity', ['value' => $this->value($latest->humidity, '%')]);
        }

        if ($this->contains($question, ['cahaya', 'ldr', 'light', '조도'])) {
            return __('ui.chat.answers.light', ['value' => $this->lightCondition($latest->light)]);
        }

        if ($this->contains($question, ['pembaruan terakhir', 'update terakhir', 'kapan data', 'terakhir diperbarui', 'last update', 'last updated', '마지막 업데이트'])) {
            return __('ui.chat.answers.updated', ['value' => $updated ?: $unavailable]);
        }

        if ($this->contains($question, ['status sekarang', 'current status', '현재 상태', 'status', 'kondisi air', 'flood status', '홍수 상태'])) {
            return __('ui.chat.answers.status', [
                'status' => $statusLabel,
                'level' => $this->value($latest->water_level, '%'),
                'recommendation' => $this->recommendations->forReading($latest)['summary'],
            ]);
        }

        return null;
    }

    public function isInScope(string $message): bool
    {
        return $this->contains(Str::lower(trim($message)), [
            'j-siaga', 'banjir', 'flood', 'hidrologi', 'hydrology', 'sensor', 'status',
            'jarak air', 'distance', 'level air', 'water level', 'suhu', 'temperature',
            'kelembapan', 'humidity', 'cahaya', 'ldr', 'light', 'mendung', 'hujan',
            'cuaca', 'weather', 'bmkg', 'bpbd', 'drainase', 'selokan', 'genangan',
            'evakuasi', 'evacuation', 'keselamatan', 'safety', 'warning', 'danger', 'flood', 'safe',
        ]);
    }

    private function contains(string $haystack, array $needles): bool
    {
        return Str::contains($haystack, $needles);
    }

    private function value(int|float|null $value, string $suffix = ''): string
    {
        return $value === null ? __('ui.common.unavailable') : rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.').$suffix;
    }

    private function lightCondition(?int $value): string
    {
        $key = LightCondition::key($value);

        return $key === null ? __('ui.common.unavailable') : __('ui.sensor.light_conditions.'.$key);
    }
}
