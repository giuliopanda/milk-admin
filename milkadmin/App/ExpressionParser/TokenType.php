<?php
namespace App\ExpressionParser;

/**
 * Costanti Token e Nodo AST condivise tra Lexer, Parser, Evaluator
 */
final class TokenType
{
    // ==================== COSTANTI TOKEN ====================
    const TOKEN_NUMBER = 'NUMBER';
    const TOKEN_STRING = 'STRING';
    const TOKEN_DATE = 'DATE';
    const TOKEN_IDENTIFIER = 'IDENTIFIER';
    const TOKEN_PARAMETER = 'PARAMETER';
    const TOKEN_PLUS = 'PLUS';
    const TOKEN_MINUS = 'MINUS';
    const TOKEN_MULTIPLY = 'MULTIPLY';
    const TOKEN_DIVIDE = 'DIVIDE';
    const TOKEN_MODULO = 'MODULO';
    const TOKEN_POWER = 'POWER';
    const TOKEN_LPAREN = 'LPAREN';
    const TOKEN_RPAREN = 'RPAREN';
    const TOKEN_COMMA = 'COMMA';
    const TOKEN_ASSIGN = 'ASSIGN';
    const TOKEN_EQ = 'EQ';
    const TOKEN_NEQ = 'NEQ';
    const TOKEN_LT = 'LT';
    const TOKEN_GT = 'GT';
    const TOKEN_LTE = 'LTE';
    const TOKEN_GTE = 'GTE';
    const TOKEN_AND = 'AND';
    const TOKEN_OR = 'OR';
    const TOKEN_NOT = 'NOT';
    const TOKEN_IF = 'IF';
    const TOKEN_THEN = 'THEN';
    const TOKEN_ELSE = 'ELSE';
    const TOKEN_ENDIF = 'ENDIF';
    const TOKEN_NEWLINE = 'NEWLINE';
    const TOKEN_EOF = 'EOF';

    // ==================== COSTANTI NODO AST ====================
    const NODE_NUMBER = 'NUMBER';
    const NODE_STRING = 'STRING';
    const NODE_DATE = 'DATE';
    const NODE_IDENTIFIER = 'IDENTIFIER';
    const NODE_PARAMETER = 'PARAMETER';
    const NODE_BINARY_OP = 'BINARY_OP';
    const NODE_UNARY_OP = 'UNARY_OP';
    const NODE_ASSIGNMENT = 'ASSIGNMENT';
    const NODE_IF_STATEMENT = 'IF_STATEMENT';
    const NODE_FUNCTION_CALL = 'FUNCTION_CALL';
    const NODE_PROGRAM = 'PROGRAM';
}
