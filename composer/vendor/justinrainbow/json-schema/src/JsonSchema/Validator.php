<?php








namespace JsonSchema;

use JsonSchema\Constraints\BaseConstraint;
use JsonSchema\Constraints\Constraint;









class Validator extends BaseConstraint
{
const SCHEMA_MEDIA_TYPE = 'application/schema+json';

const ERROR_NONE = 0x00000000;
const ERROR_ALL = 0xFFFFFFFF;
const ERROR_DOCUMENT_VALIDATION = 0x00000001;
const ERROR_SCHEMA_VALIDATION = 0x00000002;








public function validate(&$value, $schema = null, $checkMode = null)
{

if (is_array($schema)) {
$schema = self::arrayToObjectRecursive($schema);
}


$initialCheckMode = $this->factory->getConfig();
if ($checkMode !== null) {
$this->factory->setConfig($checkMode);
}


if (is_object($schema) && property_exists($schema, 'id')) {
$schemaURI = $schema->id;
} else {
$schemaURI = SchemaStorage::INTERNAL_PROVIDED_SCHEMA_URI;
}
$this->factory->getSchemaStorage()->addSchema($schemaURI, $schema);

$validator = $this->factory->createInstanceFor('schema');
$validator->check(
$value,
$this->factory->getSchemaStorage()->getSchema($schemaURI)
);

$this->factory->setConfig($initialCheckMode);

$this->addErrors(array_unique($validator->getErrors(), SORT_REGULAR));

return $validator->getErrorMask();
}




public function check($value, $schema)
{
return $this->validate($value, $schema);
}




public function coerce(&$value, $schema)
{
return $this->validate($value, $schema, Constraint::CHECK_MODE_COERCE_TYPES);
}
}
