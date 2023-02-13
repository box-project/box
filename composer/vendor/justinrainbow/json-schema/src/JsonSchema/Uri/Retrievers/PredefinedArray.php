<?php

namespace JsonSchema\Uri\Retrievers;

use JsonSchema\Validator;













class PredefinedArray extends AbstractRetriever
{





private $schemas;







public function __construct(array $schemas, $contentType = Validator::SCHEMA_MEDIA_TYPE)
{
$this->schemas = $schemas;
$this->contentType = $contentType;
}






public function retrieve($uri)
{
if (!array_key_exists($uri, $this->schemas)) {
throw new \JsonSchema\Exception\ResourceNotFoundException(sprintf(
'The JSON schema "%s" was not found.',
$uri
));
}

return $this->schemas[$uri];
}
}
