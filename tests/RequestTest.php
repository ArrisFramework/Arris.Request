<?php

declare(strict_types=1);

namespace Tests;

use Arris\Request;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;

class RequestTest extends TestCase
{
    private array $data;

    protected function setUp(): void
    {
        $this->data = [
            'name'       => '  Alice  ',
            'age'        => '42',
            'score'      => '3.14',
            'active'     => '1',
            'tags'       => ['php', 'arrイs'],
            'empty_tags' => [],
            'dishes'     => [1 => ['status' => 'insert', 'title' => 'foo']],
            'empty'      => '',
            'email_good' => 'user@example.com',
            'email_bad'  => 'not-an-email',
            'url_good'   => 'https://example.com',
            'url_bad'    => '://bad',
            'zero'       => '0',
            'no'         => 'no',
            'on'         => 'on',
            'html'       => '<b>Hello</b> <i>world</i>',
            'html_attr'  => '<a href="x" onclick="evil()">link</a>',
            'void_html'  => '<p><br></p>',
            'special'    => 'Tom & Jerry < 10',
            'whitespace' => "  a\n   b\t c  ",
        ];
    }

    // ── static factories ──────────────────────────────────────────

    #[Test]
    public function fromCreatesInstance(): void
    {
        $r = Request::from('age', $this->data);
        $this->assertInstanceOf(Request::class, $r);
    }

    #[Test]
    public function strShorthandReturnsString(): void
    {
        $this->assertSame('42', Request::str('age', $this->data));
    }

    #[Test]
    public function constructorCreatesInstance(): void
    {
        $r = new Request('age', $this->data);
        $this->assertSame('42', $r->asString());
    }

    #[Test]
    public function invokeCreatesNewInstance(): void
    {
        $r = Request::from('age', $this->data);
        $r2 = $r('tags');
        $this->assertNotSame($r, $r2);
        $this->assertSame(['php', 'arrイs'], $r2->asArray());
    }

    // ── asString / asStr ──────────────────────────────────────────

    #[Test]
    public function asStringReturnsRawString(): void
    {
        $this->assertSame('  Alice  ', Request::from('name', $this->data)->asString());
    }

    #[Test]
    public function asStrIsAlias(): void
    {
        $this->assertSame(
            Request::from('name', $this->data)->asString(),
            Request::from('name', $this->data)->asStr()
        );
    }

    #[Test]
    public function asStringTrimsIfRequested(): void
    {
        $this->assertSame('Alice', Request::from('name', $this->data)->trim()->asString());
    }

    #[Test]
    public function asStringRespectsMaxLength(): void
    {
        $this->assertSame('Ali', Request::from('name', $this->data)->trim()->maxLength(3)->asString());
    }

    #[Test]
    public function asStringReturnsDefaultWhenMissing(): void
    {
        $this->assertSame('n/a', Request::from('missing', $this->data)->default('n/a')->asString());
    }

    #[Test]
    public function asStringReturnsEmptyForMissingWithoutDefault(): void
    {
        $this->assertSame('', Request::from('missing', $this->data)->asString());
    }

    #[Test]
    public function asStringReturnsDefaultWhenEmptyNotAllowed(): void
    {
        $this->assertSame('fallback', Request::from('empty', $this->data)->allowEmpty(false)->default('fallback')->asString());
    }

    #[Test]
    public function asStringWithApplyTransform(): void
    {
        $result = Request::from('name', $this->data)
            ->trim()
            ->apply(fn($v) => strtoupper($v))
            ->asString();
        $this->assertSame('ALICE', $result);
    }

    #[Test]
    public function asStringChainsMultipleSteps(): void
    {
        $result = Request::from('name', $this->data)
            ->trim()
            ->maxLength(3)
            ->apply(fn($v) => strtoupper($v))
            ->asString();
        $this->assertSame('ALI', $result);
    }

    // ── asInt ─────────────────────────────────────────────────────

    #[Test]
    public function asIntReturnsInt(): void
    {
        $this->assertSame(42, Request::from('age', $this->data)->asInt());
    }

    #[Test]
    public function asIntReturnsDefaultForNonNumeric(): void
    {
        $this->assertSame(0, Request::from('name', $this->data)->asInt());
    }

    #[Test]
    public function asIntReturnsCustomDefault(): void
    {
        $this->assertSame(-1, Request::from('name', $this->data)->default(-1)->asInt());
    }

    #[Test]
    public function asIntZeroIsValid(): void
    {
        $this->assertSame(0, Request::from('zero', $this->data)->asInt());
    }

    // ── asFloat ───────────────────────────────────────────────────

    #[Test]
    public function asFloatReturnsFloat(): void
    {
        $this->assertSame(3.14, Request::from('score', $this->data)->asFloat());
    }

    #[Test]
    public function asFloatReturnsDefaultForNonNumeric(): void
    {
        $this->assertSame(0.0, Request::from('name', $this->data)->asFloat());
    }

    // ── asBool ────────────────────────────────────────────────────

    #[Test]
    public function asBoolTruthyString(): void
    {
        $this->assertTrue(Request::from('active', $this->data)->asBool());
        $this->assertTrue(Request::from('on', $this->data)->asBool());
    }

    #[Test]
    public function asBoolFalsyString(): void
    {
        $this->assertFalse(Request::from('no', $this->data)->asBool());
        $this->assertFalse(Request::from('zero', $this->data)->asBool());
        $this->assertFalse(Request::from('empty', $this->data)->asBool());
    }

    #[Test]
    public function asBoolDefaultForUnknown(): void
    {
        $this->assertTrue(Request::from('missing', $this->data)->default(true)->asBool());
    }

    // ── asArray ───────────────────────────────────────────────────

    #[Test]
    public function asArrayReturnsArray(): void
    {
        $this->assertSame(['php', 'arrイs'], Request::from('tags', $this->data)->asArray());
    }

    #[Test]
    public function asArrayReturnsNestedArrayAsIs(): void
    {
        $expected = [1 => ['status' => 'insert', 'title' => 'foo']];
        $this->assertSame($expected, Request::from('dishes', $this->data)->asArray());
    }

    #[Test]
    public function asArrayReturnsDefaultForEmptyArrayByDefault(): void
    {
        $this->assertSame(['x'], Request::from('empty_tags', $this->data)->default(['x'])->asArray());
    }

    #[Test]
    public function asArrayReturnsEmptyArrayWhenAllowed(): void
    {
        $this->assertSame([], Request::from('empty_tags', $this->data)->allowEmptyArray()->asArray());
    }

    #[Test]
    public function asArrayReturnsDefaultForScalar(): void
    {
        $this->assertSame(['x'], Request::from('age', $this->data)->default(['x'])->asArray());
    }

    // ── asEmail ───────────────────────────────────────────────────

    #[Test]
    public function asEmailValid(): void
    {
        $this->assertSame('user@example.com', Request::from('email_good', $this->data)->asEmail());
    }

    #[Test]
    public function asEmailInvalidReturnsDefault(): void
    {
        $this->assertSame('none', Request::from('email_bad', $this->data)->default('none')->asEmail());
    }

    // ── asUrl ─────────────────────────────────────────────────────

    #[Test]
    public function asUrlValid(): void
    {
        $this->assertSame('https://example.com', Request::from('url_good', $this->data)->asUrl());
    }

    #[Test]
    public function asUrlInvalidReturnsDefault(): void
    {
        $this->assertSame('', Request::from('url_bad', $this->data)->asUrl());
    }

    // ── asCheckbox ────────────────────────────────────────────────

    #[Test]
    public function asCheckboxReturnsInt(): void
    {
        $this->assertSame(1, Request::from('active', $this->data)->asCheckbox());
        $this->assertSame(0, Request::from('no', $this->data)->asCheckbox());
    }

    // ── asText ────────────────────────────────────────────────────

    #[Test]
    public function asTextStripsTags(): void
    {
        $this->assertSame('Hello world', Request::from('html', $this->data)->asText());
    }

    #[Test]
    public function asTextRemovesAttributesAndScripts(): void
    {
        $result = Request::from('html_attr', $this->data)->asText();
        $this->assertSame('link', $result);
        $this->assertStringNotContainsString('onclick', $result);
    }

    #[Test]
    public function asTextEscapesSpecialChars(): void
    {
        $this->assertSame('Tom &amp; Jerry &lt; 10', Request::from('special', $this->data)->asText());
    }

    #[Test]
    public function asTextKeepsHtmlWhenAllowed(): void
    {
        $this->assertSame('<b>Hello</b> <i>world</i>', Request::from('html', $this->data)->allowHtml()->asText());
    }

    #[Test]
    public function asTextRemovesVoidContent(): void
    {
        $this->assertSame('', Request::from('void_html', $this->data)->asText());
    }

    #[Test]
    public function asTextKeepsTagWhenHtmlAllowed(): void
    {
        $this->assertSame('<p><br></p>', Request::from('void_html', $this->data)->allowHtml()->noEmptyContent(false)->asText());
    }

    #[Test]
    public function asTextCollapsesWhitespace(): void
    {
        $this->assertSame('a b c', Request::from('whitespace', $this->data)->asText());
    }

    // ── raw ───────────────────────────────────────────────────────

    #[Test]
    public function rawReturnsUntouchedValue(): void
    {
        $this->assertSame('  Alice  ', Request::from('name', $this->data)->raw());
    }

    // ── defaults and missing ──────────────────────────────────────

    #[Test]
    public function missingFieldReturnsDefaultString(): void
    {
        $this->assertSame('fallback', Request::from('nonexistent', $this->data)->default('fallback')->asString());
    }

    #[Test]
    public function missingFieldReturnsDefaultInt(): void
    {
        $this->assertSame(99, Request::from('nonexistent', $this->data)->default(99)->asInt());
    }

    #[Test]
    public function missingFieldReturnsDefaultArray(): void
    {
        $this->assertSame([], Request::from('nonexistent', $this->data)->default([])->asArray());
    }

    // ── pipeline order ────────────────────────────────────────────

    #[Test]
    public function pipelineRunsInRegistrationOrder(): void
    {
        $result = Request::from('name', $this->data)
            ->trim()
            ->maxLength(3)
            ->apply(fn($v) => $v . '!')
            ->apply(fn($v) => strtoupper($v))
            ->asString();
        $this->assertSame('ALI!', $result);
    }

    #[Test]
    public function applyAcceptsStringCallable(): void
    {
        $result = Request::from('name', $this->data)
            ->trim()
            ->apply('strtolower')
            ->asString();
        $this->assertSame('alice', $result);
    }

    #[Test]
    public function stripTagsStepRemovesMarkup(): void
    {
        $result = Request::from('html', $this->data)
            ->stripTags()
            ->asString();
        $this->assertSame('Hello world', $result);
    }

    #[Test]
    public function stripTagsStepComposesWithApply(): void
    {
        $result = Request::from('html', $this->data)
            ->stripTags()
            ->apply(fn($v) => strtoupper($v))
            ->asString();
        $this->assertSame('HELLO WORLD', $result);
    }

    #[Test]
    public function substrStepSlicesString(): void
    {
        $result = Request::from('name', $this->data)
            ->trim()
            ->substr(1, 3)
            ->asString();
        $this->assertSame('lic', $result);
    }

    #[Test]
    public function substrStepWithoutLengthSlicesToEnd(): void
    {
        $result = Request::from('name', $this->data)
            ->trim()
            ->substr(2)
            ->asString();
        $this->assertSame('ice', $result);
    }
}
