<?php

/**
 * REDAXO Media Manager Effekt
 * Konvertiert Videos in animierte WebP oder MP4 Vorschauen 
 * mit optimierter Textdarstellung und korrekter Positionierung
 */
class rex_effect_video_to_preview extends rex_effect_abstract
{
    private const COMPRESSION_LEVELS = [
        1 => 'Minimal (große Datei, beste Qualität)',
        2 => 'Niedrig (bessere Qualität)',
        3 => 'Standard (ausgewogen)',
        4 => 'Hoch (kleine Datei)',
        5 => 'Maximal (kleinste Datei)'
    ];

    private const OUTPUT_FORMATS = [
        'webp' => 'Animiertes WebP',
        'mp4' => 'MP4 Video (ohne Ton)'
    ];

    private const MAX_DURATION = 10;
    private const VIDEO_TYPES = ['mp4', 'm4v', 'avi', 'mov', 'webm'];
    private const START_OFFSET = 2;
    private const END_OFFSET = 10;

    public function execute()
    {
        try {
            $inputFile = rex_type::notNull($this->media->getMediaPath());
            
            if (!$this->isVideoFile($inputFile)) {
                return;
            }

            if (!$this->isFfmpegAvailable()) {
                throw new rex_exception('FFmpeg ist nicht verfügbar');
            }

            $params = $this->validateAndGetParams();
            
            $duration = $this->getVideoDuration($inputFile);
            if ($duration <= 0) {
                throw new rex_exception('Videolänge konnte nicht ermittelt werden');
            }
            
            rex_logger::factory()->log('media_manager', sprintf(
                'Video Info: Länge=%f, Position=%s, SnippetLänge=%f', 
                $duration, 
                $params['position'], 
                $params['snippetLength']
            ));
            
            $startPosition = $this->calculateStartPosition(
                $duration,
                $params['snippetLength'],
                $params['position']
            );
            
            rex_logger::factory()->log('media_manager', sprintf(
                'Berechnete Startposition: %f Sekunden', 
                $startPosition
            ));

            $extension = $params['format'] === 'mp4' ? 'mp4' : 'webp';
            $outputFile = rex_path::addonCache('media_manager', 
                'media_manager__video_preview_' . md5($inputFile) . '.' . $extension);

            if ($params['format'] === 'mp4') {
                $this->convertToMp4(
                    $inputFile,
                    $outputFile,
                    $startPosition,
                    $params['snippetLength'],
                    $params['width'],
                    $params['fps'],
                    $params['quality'],
                    $params['compression']
                );
            } else {
                $this->convertToWebp(
                    $inputFile,
                    $outputFile,
                    $startPosition,
                    $params['snippetLength'],
                    $params['width'],
                    $params['fps'],
                    $params['quality'],
                    $params['compression']
                );
            }

            if (!file_exists($outputFile) || filesize($outputFile) === 0) {
                throw new rex_exception('Ausgabedatei ist leer oder existiert nicht');
            }

            $this->media->setSourcePath($outputFile);
            $this->media->refreshImageDimensions();
            $this->media->setFormat($extension);
            $this->media->setHeader('Content-Type', 'image/' . $extension);
            
            register_shutdown_function(static function() use ($outputFile) {
                rex_file::delete($outputFile);
            });

        } catch (rex_exception $e) {
            rex_logger::factory()->logException($e);
            return;
        }
    }

    private function validateAndGetParams(): array
    {
        rex_logger::factory()->log('media_manager', sprintf(
            'Raw Params Debug: position=%s', 
            $this->params['position'] ?? 'nicht gesetzt'
        ));

        return [
            'width' => max(1, intval($this->params['width'] ?? 400)),
            'fps' => max(1, min(30, intval($this->params['fps'] ?? 12))),
            'quality' => $this->getQualityForCompression(intval($this->params['compression_level'] ?? 3)),
            'snippetLength' => min(floatval($this->params['snippet_length'] ?? 2), self::MAX_DURATION),
            'compression' => max(1, min(5, intval($this->params['compression_level'] ?? 3))),
            'position' => $this->normalizePosition($this->params['position'] ?? 'middle'),
            'format' => $this->params['output_format'] ?? 'webp'
        ];
    }

    private function normalizePosition(string $position): string
    {
        switch ($position) {
            case 'Anfang (nach 2 Sekunden)':
            case 'start':
                return 'start';
            case '10 Sekunden vor Ende':
            case 'end':
                return 'end';
            case 'Mitte des Videos':
            case 'middle':
            default:
                return 'middle';
        }
    }

    private function calculateStartPosition(float $duration, float $snippetLength, string $position): float
    {
        $snippetLength = min($snippetLength, $duration);
        
        rex_logger::factory()->log('media_manager', sprintf(
            'Position Debug: position=%s, duration=%f, snippetLength=%f', 
            $position,
            $duration,
            $snippetLength
        ));

        switch ($position) {
            case 'start':
                if ($duration > (self::START_OFFSET + $snippetLength)) {
                    return self::START_OFFSET;
                }
                return 0.0;

            case 'end':
                if ($duration <= (self::END_OFFSET + $snippetLength)) {
                    return max(0.0, $duration - $snippetLength);
                }
                return max(0.0, $duration - self::END_OFFSET - $snippetLength);

            case 'middle':
            default:
                $middlePoint = $duration / 2;
                return max(0.0, min(
                    $middlePoint - ($snippetLength / 2),
                    $duration - $snippetLength
                ));
        }
    }

    private function getQualityForCompression(int $compression): int 
    {
        $qualityMap = [
            1 => 95, // Minimal
            2 => 85, // Niedrig
            3 => 75, // Standard
            4 => 65, // Hoch
            5 => 55  // Maximal
        ];
        
        return $qualityMap[$compression] ?? 75;
    }

    private function convertToMp4($input, $output, $start, $length, $width, $fps, $quality, $compression)
    {
        $filters = [];
        
        if ($compression > 3) {
            $filters[] = 'unsharp=3:3:0.3:3:3:0.1';
        }
        
        $filters[] = sprintf('scale=%d:-1:flags=lanczos+accurate_rnd', $width);
        $filters[] = 'eq=contrast=1.1';
        
        if ($compression <= 3) {
            $filters[] = 'unsharp=5:5:1.0:5:5:0.0';
        }
        
        $filters[] = sprintf('fps=%d', $fps);
        $filters[] = 'crop=trunc(iw/2)*2:trunc(ih/2)*2';

        $crf = min(28, 18 + ($compression * 2));

        $cmd = sprintf(
            'ffmpeg -y ' .
            '-ss %f -t %f '.
            '-i %s '.
            '-vf "%s" '.
            '-c:v libx264 '.
            '-preset medium '.
            '-crf %d '.
            '-profile:v main '.
            '-movflags +faststart '.
            '-an '.
            '-threads 4 '.
            '%s 2>&1',
            $start,
            $length,
            escapeshellarg($input),
            implode(',', $filters),
            $crf,
            escapeshellarg($output)
        );

        $this->executeCommand($cmd);
    }

    private function convertToWebp($input, $output, $start, $length, $width, $fps, $quality, $compression)
    {
        $filters = [];
        
        if ($compression > 3) {
            $filters[] = 'unsharp=3:3:0.3:3:3:0.1';
        }
        
        $filters[] = sprintf('scale=%d:-1:flags=lanczos+accurate_rnd', $width);
        $filters[] = 'eq=contrast=1.1';
        
        if ($compression <= 3) {
            $filters[] = 'unsharp=5:5:1.0:5:5:0.0';
        }
        
        $filters[] = sprintf('fps=%d', $fps);
        $filters[] = 'crop=trunc(iw/2)*2:trunc(ih/2)*2';

        $cmd = sprintf(
            'ffmpeg -y ' .
            '-ss %f -t %f '.
            '-i %s '.
            '-vf "%s" '.
            '-vcodec libwebp '.
            '-preset picture '.
            '-compression_level %d '. 
            '-lossless 0 '.
            '-quality %d '.
            '-loop 0 '.
            '-vsync 0 '.
            '-qmin %d '.
            '-qmax %d '.
            '-metadata author="" '.
            '-an -threads 4 '.
            '%s 2>&1',
            $start,
            $length,
            escapeshellarg($input),
            implode(',', $filters),
            min(4, $compression),
            $quality,
            max(1, $compression),
            min(20, $compression * 4),
            escapeshellarg($output)
        );

        $this->executeCommand($cmd);
    }

    private function executeCommand($cmd)
    {
        exec($cmd, $output, $returnCode);
        
        if ($returnCode !== 0) {
            throw new rex_exception(
                'Befehl fehlgeschlagen: ' . $cmd . "\n" . implode("\n", $output)
            );
        }
    }

    private function getVideoDuration($inputFile): float
    {
        $cmd = sprintf(
            'ffprobe -v error -select_streams v:0 '.
            '-show_entries stream=duration -of default=noprint_wrappers=1:nokey=1 %s',
            escapeshellarg($inputFile)
        );
        
        exec($cmd, $output, $returnCode);
        
        if ($returnCode !== 0 || empty($output)) {
            return 0.0;
        }
        
        return (float) $output[0];
    }

    private function isVideoFile($file): bool
    {
        if (!file_exists($file)) {
            return false;
        }

        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        return in_array($ext, self::VIDEO_TYPES);
    }

    private function isFfmpegAvailable(): bool
    {
        if (!function_exists('exec')) {
            return false;
        }
        
        exec('ffmpeg -version', $output, $returnCode);
        return $returnCode === 0;
    }

    public function getName()
    {
        return 'Video Vorschau (WebP/MP4)';
    }

    public function getParams()
    {
        $notice = '';
        if (!$this->isFfmpegAvailable()) {
            $notice = '<strong>FFmpeg wurde nicht gefunden. Dieser Effekt wird nicht funktionieren.</strong><br>';
        }

        return [
            [
                'label' => 'Ausgabeformat',
                'name' => 'output_format',
                'type' => 'select',
                'options' => self::OUTPUT_FORMATS,
                'default' => 'webp',
                'notice' => 'Wähle das gewünschte Ausgabeformat'
            ],
            [
                'label' => 'Position im Video',
                'name' => 'position',
                'type' => 'select',
                'options' => [
                    'end' => '10 Sekunden vor Ende',
                    'middle' => 'Mitte des Videos',
                    'start' => 'Anfang (nach 2 Sekunden)'
                ],
                'default' => 'middle',
                'notice' => 'Wähle die Stelle im Video, von der der Ausschnitt erstellt werden soll',
                'prefix' => $notice
            ],
            [
                'label' => 'Ausgabebreite',
                'name' => 'width',
                'type' => 'int',
                'default' => '400',
                'notice' => 'Empfohlen: 400-800px für bessere Textdarstellung'
            ],
            [
                'label' => 'Kompressionsstufe',
                'name' => 'compression_level',
                'type' => 'select',
                'options' => self::COMPRESSION_LEVELS,
                'default' => '3',
                'notice' => 'Niedrigere Kompression für bessere Textqualität wählen'
            ],
            [
                'label' => 'FPS',
                'name' => 'fps',
                'type' => 'int',
                'default' => '12',
                'notice' => 'Empfohlen: 12-15 FPS für bessere Textlesbarkeit'
            ],
            [
                'label' => 'Snippet-Länge (Sekunden)',
                'name' => 'snippet_length',
                'type' => 'int',
                'default' => '2',
                'notice' => sprintf('Maximal %d Sekunden. Empfohlen: 2-3 Sekunden', self::MAX_DURATION)
            ]
        ];
    }
}
