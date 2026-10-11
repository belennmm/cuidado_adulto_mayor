<?php

namespace Tests\Unit;

use App\Support\StrictJson;
use JsonException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

// Full repository checkout: backend/tests/Unit -> repository root (three levels).
// Backend mounted at the container root: tests/Unit -> project root (two levels).
$staticAnalysisPath = dirname(__DIR__, 3).'/tests/security/php-sast.php';
if (! is_file($staticAnalysisPath)) {
    $staticAnalysisPath = dirname(__DIR__, 2).'/tests/security/php-sast.php';
}
require_once $staticAnalysisPath;

class StrictJsonAndStaticAnalysisTest extends TestCase
{
    public function test_json_strings_do_not_confuse_key_and_depth_detection(): void
    {
        $value = StrictJson::decode(json_encode(['text' => 'a "key": [{}] \ end', 'items' => [['name' => 'A'], ['name' => 'B']]], JSON_THROW_ON_ERROR));
        $this->assertSame('A', $value->items[0]->name);
        $this->assertSame('B', $value->items[1]->name);
    }

    public function test_escaped_duplicate_keys_are_rejected(): void
    {
        $this->expectException(JsonException::class);
        StrictJson::decode('{"na\u006de":1,"name":2}');
    }

    #[DataProvider('unsafeSources')]
    public function test_static_guard_detects_unsafe_operations(string $source): void
    {
        $this->assertNotEmpty(\InjectionStaticAnalysis::findings('<?php '.$source));
    }

    public static function unsafeSources(): array
    {
        return [
            ['$q->whereRaw("name=" . $input);'],
            ['$q->orderByRaw($column);'],
            ['$q->whereRaw("name=$input");'],
            ['DB::select("SELECT " . $input);'],
            ['shell_exec($input);'],
            ['\\shell_exec($input);'],
            ['unserialize($input);'],
            ['unserialize(base64_decode($input));'],
            ['\\unserialize($input);'],
            ['eval($input);'],
            ['`whoami`;'],
            ['mail($to, $subject, $body);'],
            ['header($input);'],
            ['fputcsv($out, $row);'],
        ];
    }

    public function test_static_guard_accepts_bound_queries_and_ignores_comments_and_header_readers(): void
    {
        $this->assertSame([], \InjectionStaticAnalysis::findings('<?php /* exec($x); */ $q->whereRaw("name = ?", [$input . "x"]); $request->header("Accept");'));
    }

    public function test_json_class_metadata_and_serialized_values_remain_plain_data(): void
    {
        $serialized = 'O:8:"stdClass":0:{}';
        $value = StrictJson::decode(json_encode(['__class' => 'UnexpectedClass', 'value' => $serialized], JSON_THROW_ON_ERROR));

        $this->assertInstanceOf(\stdClass::class, $value);
        $this->assertSame('UnexpectedClass', $value->__class);
        $this->assertSame($serialized, $value->value);
        $this->assertSame([], \InjectionStaticAnalysis::findings('<?php \\json_decode($input, false, 64, JSON_THROW_ON_ERROR);'));
    }
}
