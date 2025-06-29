<?php
namespace MilkCore;

!defined('MILK_DIR') && die(); // Avoid direct access

require_once 'mathparser.php';
//require_once 'variablemathparser.php';
require_once 'data-row-manager.php';
require_once 'data-row.functions.php';
require_once 'mathematic.functions.php';
require_once 'statistical.functions.php';
require_once 'serialization.functions.php';
require_once 'datetime.functions.php';


MathParser::add_class('\MilkCore\MathematicalFunctions');
MathParser::add_class('\MilkCore\StatisticalFunctions');
MathParser::add_class('\MilkCore\DataRowFunctions');
MathParser::add_class('\MilkCore\SerializationFunctions');
MathParser::add_class('\MilkCore\DateFunctions');
