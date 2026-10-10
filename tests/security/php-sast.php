<?php

// Dependency-free guard for runtime PHP sources. It does not execute application code.
final class InjectionStaticAnalysis
{
    public static function findings(string $source): array
    {
        $tokens = array_values(array_filter(token_get_all($source), fn ($token) => ! is_array($token) || ! in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)));
        $findings = [];
        foreach ($tokens as $index => $token) {
            if ($token === '`' || (is_array($token) && $token[0] === T_EVAL)) {
                $findings[] = 'Dynamic code or shell execution';
            }
            if (! is_array($token) || $token[0] !== T_STRING || ($tokens[$index + 1] ?? null) !== '(') {
                continue;
            }
            $name = strtolower($token[1]);
            $previous = $tokens[$index - 1] ?? null;
            $isMethod = is_array($previous) && in_array($previous[0], [T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON], true);
            if (! $isMethod && in_array($name, ['exec', 'shell_exec', 'system', 'passthru', 'popen', 'proc_open', 'unserialize', 'mail', 'header', 'fputcsv'], true)) {
                $findings[] = 'Unreviewed command, serialization, mail/header or CSV sink: '.$name;
            }
            $owner = $tokens[$index - 2] ?? null;
            $databaseCall = is_array($previous) && $previous[0] === T_DOUBLE_COLON
                && is_array($owner) && strtolower($owner[1]) === 'db'
                && in_array($name, ['select', 'selectone', 'cursor', 'insert', 'update', 'delete'], true);
            if (! $databaseCall && ! in_array($name, ['raw', 'whereraw', 'orwhereraw', 'selectraw', 'orderbyraw', 'groupbyraw', 'havingraw', 'fromraw', 'statement', 'unprepared'], true)) {
                continue;
            }
            $depth = 0;
            for ($cursor = $index + 2; $cursor < count($tokens); $cursor++) {
                $part = $tokens[$cursor];
                if ($depth === 0 && ($part === ',' || $part === ')')) {
                    break;
                }
                if ($part === '(' || $part === '[') {
                    $depth++;
                }
                if ($part === ')' || $part === ']') {
                    $depth--;
                }
                if ($part === '.' || (is_array($part) && in_array($part[0], [T_VARIABLE, T_ENCAPSED_AND_WHITESPACE], true))) {
                    $findings[] = 'Dynamic SQL argument: '.$name.' at line '.$token[2];
                    break;
                }
            }
        }

        return $findings;
    }
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') !== __FILE__) {
    return;
}
$root = dirname(__DIR__, 2);
$failures = 0;
foreach (['backend/app', 'backend/routes', 'backend/database'] as $directory) {
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/'.$directory, FilesystemIterator::SKIP_DOTS));
    foreach ($files as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }
        foreach (InjectionStaticAnalysis::findings(file_get_contents($file->getPathname())) as $finding) {
            fwrite(STDERR, $file->getPathname().': '.$finding.PHP_EOL);
            $failures++;
        }
    }
}
echo $failures === 0 ? "PHP injection guard: passed\n" : "PHP injection guard: failed\n";
exit($failures === 0 ? 0 : 1);
