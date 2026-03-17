<?php

declare(strict_types=1);

use App\ExpressionParser;
use PHPUnit\Framework\TestCase;

final class ExpressionParserTest extends TestCase
{
    public function testParseReturnsProgramAst(): void
    {
        $parser = new ExpressionParser();
        $ast = $parser->parse('1 + 2');

        $this->assertSame(ExpressionParser::NODE_PROGRAM, $ast['type']);
        $this->assertNotEmpty($ast['statements']);
    }

    public function testExecuteHandlesMathAssignmentsAndParameters(): void
    {
        $parser = new ExpressionParser();

        $this->assertSame(3, $parser->execute('1 + 2'));
        $this->assertSame(7, $parser->execute("a = 5\na + 2"));

        $parser->setParameter('salary', 100);
        $this->assertSame(150, $parser->execute('[salary] + 50'));
    }

    public function testSetParametersAndResetApis(): void
    {
        $parser = new ExpressionParser();
        $parser->setParameters(['a' => 1, 'b' => 2]);

        $params = $parser->getParameters();
        $this->assertArrayHasKey('a', $params);
        $this->assertArrayHasKey('b', $params);

        $parser->execute("x = 10\ny = x + 5");
        $this->assertArrayHasKey('x', $parser->getVariables());

        $parser->reset();
        $this->assertSame([], $parser->getVariables());
        $this->assertNotSame([], $parser->getParameters());

        $parser->resetAll();
        $this->assertSame([], $parser->getVariables());
        $this->assertSame([], $parser->getParameters());
    }

    public function testAnalyzeOperationOrderAndTreeOutput(): void
    {
        $parser = new ExpressionParser();
        $analysis = $parser->analyze('z = 2 + 3', true);

        $this->assertArrayHasKey('ast', $analysis);
        $this->assertArrayHasKey('operations', $analysis);
        $this->assertArrayHasKey('tree', $analysis);
        $this->assertArrayHasKey('result', $analysis);
        $this->assertSame(5, $analysis['result']);

        $ops = $parser->getOperationOrder($analysis['ast']);
        $this->assertNotEmpty($ops);

        $tree = $parser->visualizeTree($analysis['ast']);
        $this->assertStringContainsString('PROGRAM', $tree);
    }

    public function testComponentAccessorsReturnExpectedTypes(): void
    {
        $parser = new ExpressionParser();

        $this->assertInstanceOf(\App\ExpressionParser\Lexer::class, $parser->getLexer());
        $this->assertInstanceOf(\App\ExpressionParser\Parser::class, $parser->getParser());
        $this->assertInstanceOf(\App\ExpressionParser\Evaluator::class, $parser->getEvaluator());
        $this->assertNotSame([], $parser->getBuiltinFunctions());
    }
}
