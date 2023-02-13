<?php








namespace JsonSchema\Constraints;

use JsonSchema\Entity\JsonPointer;






interface ConstraintInterface
{





public function getErrors();






public function addErrors(array $errors);









public function addError(JsonPointer $path = null, $message, $constraint='', array $more = null);






public function isValid();













public function check(&$value, $schema = null, JsonPointer $path = null, $i = null);
}
