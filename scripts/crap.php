<?php

declare(strict_types=1);

/**
 * CRAP (Change Risk Anti-Patterns) analyzer for the entire application.
 *
 * Usage:
 *   composer crap
 *   php scripts/crap.php [--skip-tests] [--threshold=30] [--top=15]
 */

$options = getopt('', ['skip-tests', 'threshold:', 'top:', 'clover:']);
$skipTests = array_key_exists('skip-tests', $options);
$threshold = isset($options['threshold']) ? (float) $options['threshold'] : 30.0;
$topLimit = isset($options['top']) ? max(1, (int) $options['top']) : 15;
$cloverPath = $options['clover'] ?? __DIR__ . '/../build/logs/clover.xml';

if (! $skipTests || ! file_exists($cloverPath)) {
    $cloverDir = dirname($cloverPath);
    if (! is_dir($cloverDir)) {
        mkdir($cloverDir, 0775, true);
    }

    echo "\033[1;34m▶ Ejecutando tests con cobertura (Pest + PCOV)...\033[0m\n";
    $cmd = sprintf('./vendor/bin/pest --coverage-clover %s', escapeshellarg($cloverPath));
    passthru($cmd, $exitCode);

    if ($exitCode !== 0) {
        fwrite(STDERR, "\033[1;31m✖ Error al ejecutar los tests (código $exitCode).\033[0m\n");
        exit($exitCode);
    }
    echo "\n";
}

if (! file_exists($cloverPath)) {
    fwrite(STDERR, "\033[1;31m✖ No se encontró el archivo Clover XML en: {$cloverPath}\033[0m\n");
    exit(1);
}

$xml = simplexml_load_file($cloverPath);
if ($xml === false) {
    fwrite(STDERR, "\033[1;31m✖ No se pudo parsear el archivo Clover XML.\033[0m\n");
    exit(1);
}

/**
 * Calcula el índice CRAP a partir de la complejidad ciclomática y el porcentaje de cobertura (0-100).
 */
function calculateCrap(int $ccn, float $coveragePercent): float
{
    if ($ccn <= 0) {
        return 0.0;
    }
    if ($coveragePercent >= 100.0) {
        return (float) $ccn;
    }

    $untested = 1.0 - ($coveragePercent / 100.0);

    return ($ccn ** 2) * ($untested ** 3) + $ccn;
}

/**
 * Calcula el CRAP Load (esfuerzo necesario para bajar del umbral CRAP).
 */
function calculateCrapLoad(float $crap, int $ccn, float $coveragePercent, float $threshold): float
{
    if ($crap < $threshold) {
        return 0.0;
    }

    return ($ccn * (1.0 - ($coveragePercent / 100.0))) + ($ccn / $threshold);
}

$methods = [];
$classes = [];
$totalComplexity = 0;
$totalCrap = 0.0;
$totalCrapLoad = 0.0;
$crapMethodCount = 0;

foreach ($xml->xpath('//file') as $fileNode) {
    $filePath = (string) $fileNode['name'];
    $relativePath = str_replace(realpath(__DIR__ . '/..') . '/', '', $filePath);

    $classNode = $fileNode->class[0] ?? null;
    $className = $classNode ? (string) $classNode['name'] : $relativePath;
    $classMetrics = $classNode ? $classNode->metrics[0] : $fileNode->metrics[0];

    $classComplexity = $classMetrics ? (int) $classMetrics['complexity'] : 0;
    $classStatements = $classMetrics ? (int) $classMetrics['statements'] : 0;
    $classCoveredStatements = $classMetrics ? (int) $classMetrics['coveredstatements'] : 0;
    $classCoverage = $classStatements > 0 ? ($classCoveredStatements / $classStatements) * 100.0 : 100.0;

    // Recopilar líneas para calcular cobertura por método
    $lines = [];
    foreach ($fileNode->line as $lineNode) {
        $lines[] = $lineNode;
    }

    $fileMethodsCrapSum = 0.0;
    $fileMethodsMaxCrap = 0.0;
    $fileCrappyMethods = 0;
    $fileMethodsCount = 0;

    for ($i = 0, $n = count($lines); $i < $n; $i++) {
        $line = $lines[$i];
        if ((string) $line['type'] !== 'method') {
            continue;
        }

        $methodName = (string) $line['name'];
        $lineNum = (int) $line['num'];
        $ccn = (int) $line['complexity'];
        $crap = (float) $line['crap'];

        // Contar statements hasta el próximo método
        $stmtTotal = 0;
        $stmtCovered = 0;
        for ($j = $i + 1; $j < $n; $j++) {
            if ((string) $lines[$j]['type'] === 'method') {
                break;
            }
            if ((string) $lines[$j]['type'] === 'stmt') {
                $stmtTotal++;
                if ((int) $lines[$j]['count'] > 0) {
                    $stmtCovered++;
                }
            }
        }

        $methodCoverage = $stmtTotal > 0
            ? ($stmtCovered / $stmtTotal) * 100.0
            : ((int) $line['count'] > 0 ? 100.0 : 0.0);

        $crapLoad = calculateCrapLoad($crap, $ccn, $methodCoverage, $threshold);

        $methods[] = [
            'class' => $className,
            'method' => $methodName,
            'full' => "{$className}::{$methodName}",
            'file' => $relativePath,
            'line' => $lineNum,
            'ccn' => $ccn,
            'coverage' => $methodCoverage,
            'crap' => $crap,
            'crapLoad' => $crapLoad,
        ];

        $totalComplexity += $ccn;
        $totalCrap += $crap;
        $totalCrapLoad += $crapLoad;
        $fileMethodsCrapSum += $crap;
        $fileMethodsMaxCrap = max($fileMethodsMaxCrap, $crap);
        $fileMethodsCount++;

        if ($crap >= $threshold) {
            $crapMethodCount++;
            $fileCrappyMethods++;
        }
    }

    if ($classNode || $fileMethodsCount > 0) {
        if ($classComplexity === 0 && $fileMethodsCount > 0) {
            // Fallback en caso de que el nodo class no reporte complexity
            $classComplexity = array_sum(array_column(
                array_filter($methods, fn ($m) => $m['class'] === $className),
                'ccn'
            ));
        }

        $classCrap = calculateCrap($classComplexity, $classCoverage);

        $classes[] = [
            'class' => $className,
            'file' => $relativePath,
            'methods' => $fileMethodsCount,
            'ccn' => $classComplexity,
            'coverage' => $classCoverage,
            'classCrap' => $classCrap,
            'sumMethodCrap' => $fileMethodsCrapSum,
            'maxMethodCrap' => $fileMethodsMaxCrap,
            'crappyMethods' => $fileCrappyMethods,
        ];
    }
}

$projectMetrics = $xml->project->metrics[0];
$totalFiles = (int) $projectMetrics['files'];
$totalClasses = count($classes);
$totalMethods = count($methods);
$totalStatements = (int) $projectMetrics['statements'];
$coveredStatements = (int) $projectMetrics['coveredstatements'];
$appCoverage = $totalStatements > 0 ? ($coveredStatements / $totalStatements) * 100.0 : 0.0;
$avgMethodCrap = $totalMethods > 0 ? $totalCrap / $totalMethods : 0.0;
$crapMethodPercent = $totalMethods > 0 ? ($crapMethodCount / $totalMethods) * 100.0 : 0.0;

usort($methods, fn ($a, $b) => $b['crap'] <=> $a['crap'] ?: $b['ccn'] <=> $a['ccn']);
usort($classes, fn ($a, $b) => $b['classCrap'] <=> $a['classCrap'] ?: $b['sumMethodCrap'] <=> $a['sumMethodCrap']);

$maxMethodCrap = $methods[0]['crap'] ?? 0.0;

// Helpers de color ANSI
function colorForCrap(float $crap, float $threshold): string
{
    if ($crap >= $threshold) {
        return "\033[1;31m"; // Rojo
    }
    if ($crap >= $threshold / 2) {
        return "\033[1;33m"; // Amarillo
    }

    return "\033[0;32m"; // Verde
}

$reset = "\033[0m";
$bold = "\033[1m";
$cyan = "\033[1;36m";

echo "{$cyan}========================================================================================{$reset}\n";
echo "{$bold}                       REPORTE GLOBAL DE CRAP DE LA APLICACIÓN                          {$reset}\n";
echo "{$cyan}========================================================================================{$reset}\n";
printf("  Archivos analizados       : %d\n", $totalFiles);
printf("  Clases / Unidades         : %d\n", $totalClasses);
printf("  Métodos totales           : %d\n", $totalMethods);
printf("  Complejidad Ciclomática   : %d (promedio %.2f por método)\n", $totalComplexity, $totalMethods > 0 ? $totalComplexity / $totalMethods : 0);
printf("  Cobertura de sentencias   : %.2f%% (%d / %d)\n", $appCoverage, $coveredStatements, $totalStatements);
echo "----------------------------------------------------------------------------------------\n";
printf("  {$bold}CRAP Total (suma métodos){$reset} : %.2f\n", $totalCrap);
printf("  {$bold}CRAP Promedio por método{$reset}  : %s%.2f{$reset}\n", colorForCrap($avgMethodCrap, $threshold), $avgMethodCrap);
printf("  {$bold}CRAP Máximo (peor método){$reset} : %s%.2f{$reset}\n", colorForCrap($maxMethodCrap, $threshold), $maxMethodCrap);
printf("  {$bold}Métodos críticos (>= %d){$reset}  : %s%d de %d (%.2f%%){$reset} [Meta recomendada: < 5.00%%]\n", (int) $threshold, $crapMethodPercent >= 5.0 ? "\033[1;31m" : "\033[0;32m", $crapMethodCount, $totalMethods, $crapMethodPercent);
printf("  {$bold}CRAP Load (deuda técnica){$reset} : %.0f\n", round($totalCrapLoad));
echo "{$cyan}========================================================================================{$reset}\n\n";

echo "{$bold}TOP {$topLimit} MÉTODOS CON MAYOR ÍNDICE CRAP:{$reset}\n";
echo str_repeat('-', 105) . "\n";
printf("  %-8s | %-5s | %-9s | %-9s | %s\n", 'CRAP', 'CCN', 'Cobertura', 'CRAP Load', 'Método (Archivo:Línea)');
echo str_repeat('-', 105) . "\n";

foreach (array_slice($methods, 0, $topLimit) as $m) {
    $color = colorForCrap($m['crap'], $threshold);
    printf(
        "  %s%8.2f{$reset} | %5d | %8.1f%% | %9.1f | %s (%s:%d)\n",
        $color,
        $m['crap'],
        $m['ccn'],
        $m['coverage'],
        $m['crapLoad'],
        $m['full'],
        $m['file'],
        $m['line']
    );
}
echo str_repeat('-', 105) . "\n\n";

echo "{$bold}TOP {$topLimit} CLASES CON MAYOR RIESGO (CRAP):{$reset}\n";
echo str_repeat('-', 105) . "\n";
printf("  %-10s | %-10s | %-5s | %-9s | %-11s | %s\n", 'CRAP Clase', 'Σ CRAP Mét', 'CCN', 'Cobertura', 'Mét >= ' . (int) $threshold, 'Clase');
echo str_repeat('-', 105) . "\n";

foreach (array_slice($classes, 0, $topLimit) as $c) {
    $color = colorForCrap($c['maxMethodCrap'], $threshold);
    printf(
        "  %s%10.2f{$reset} | %10.2f | %5d | %8.1f%% | %5d / %-3d | %s\n",
        $color,
        $c['classCrap'],
        $c['sumMethodCrap'],
        $c['ccn'],
        $c['coverage'],
        $c['crappyMethods'],
        $c['methods'],
        $c['class']
    );
}
echo str_repeat('-', 105) . "\n";

