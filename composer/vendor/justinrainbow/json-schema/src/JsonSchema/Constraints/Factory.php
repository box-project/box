<?php








namespace JsonSchema\Constraints;

use JsonSchema\Exception\InvalidArgumentException;
use JsonSchema\SchemaStorage;
use JsonSchema\SchemaStorageInterface;
use JsonSchema\Uri\UriRetriever;
use JsonSchema\UriRetrieverInterface;
use JsonSchema\Validator;




class Factory
{



protected $schemaStorage;




protected $uriRetriever;




private $checkMode = Constraint::CHECK_MODE_NORMAL;




private $typeCheck = array();




protected $errorContext = Validator::ERROR_DOCUMENT_VALIDATION;




protected $constraintMap = array(
'array' => 'JsonSchema\Constraints\CollectionConstraint',
'collection' => 'JsonSchema\Constraints\CollectionConstraint',
'object' => 'JsonSchema\Constraints\ObjectConstraint',
'type' => 'JsonSchema\Constraints\TypeConstraint',
'undefined' => 'JsonSchema\Constraints\UndefinedConstraint',
'string' => 'JsonSchema\Constraints\StringConstraint',
'number' => 'JsonSchema\Constraints\NumberConstraint',
'enum' => 'JsonSchema\Constraints\EnumConstraint',
'format' => 'JsonSchema\Constraints\FormatConstraint',
'schema' => 'JsonSchema\Constraints\SchemaConstraint',
'validator' => 'JsonSchema\Validator'
);




private $instanceCache = array();






public function __construct(
SchemaStorageInterface $schemaStorage = null,
UriRetrieverInterface $uriRetriever = null,
$checkMode = Constraint::CHECK_MODE_NORMAL
) {

$this->setConfig($checkMode);

$this->uriRetriever = $uriRetriever ?: new UriRetriever();
$this->schemaStorage = $schemaStorage ?: new SchemaStorage($this->uriRetriever);
}






public function setConfig($checkMode = Constraint::CHECK_MODE_NORMAL)
{
$this->checkMode = $checkMode;
}






public function addConfig($options)
{
$this->checkMode |= $options;
}






public function removeConfig($options)
{
$this->checkMode &= ~$options;
}








public function getConfig($options = null)
{
if ($options === null) {
return $this->checkMode;
}

return $this->checkMode & $options;
}




public function getUriRetriever()
{
return $this->uriRetriever;
}

public function getSchemaStorage()
{
return $this->schemaStorage;
}

public function getTypeCheck()
{
if (!isset($this->typeCheck[$this->checkMode])) {
$this->typeCheck[$this->checkMode] = ($this->checkMode & Constraint::CHECK_MODE_TYPE_CAST)
? new TypeCheck\LooseTypeCheck()
: new TypeCheck\StrictTypeCheck();
}

return $this->typeCheck[$this->checkMode];
}







public function setConstraintClass($name, $class)
{

if (!class_exists($class)) {
throw new InvalidArgumentException('Unknown constraint ' . $name);
}

if (!in_array('JsonSchema\Constraints\ConstraintInterface', class_implements($class))) {
throw new InvalidArgumentException('Invalid class ' . $name);
}
$this->constraintMap[$name] = $class;

return $this;
}










public function createInstanceFor($constraintName)
{
if (!isset($this->constraintMap[$constraintName])) {
throw new InvalidArgumentException('Unknown constraint ' . $constraintName);
}

if (!isset($this->instanceCache[$constraintName])) {
$this->instanceCache[$constraintName] = new $this->constraintMap[$constraintName]($this);
}

return clone $this->instanceCache[$constraintName];
}






public function getErrorContext()
{
return $this->errorContext;
}






public function setErrorContext($errorContext)
{
$this->errorContext = $errorContext;
}
}
