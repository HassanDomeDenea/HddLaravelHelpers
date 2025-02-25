<?php

use HassanDomeDenea\HddLaravelHelpers\Attributes\ResponseAttribute;
use HassanDomeDenea\HddLaravelHelpers\Controllers\BatchRequestController;
use phpDocumentor\Reflection\DocBlockFactory;

it('can convert response properties to typescript', function () {


    $docBlockFactory = DocBlockFactory::createInstance();;
    /* $attribute = new ResponseAttribute('',
         ['user' => 'string', 'token' => 'string']
     );*/


    $reflection = new ReflectionClass(BatchRequestController::class);
    $method = $reflection->getMethods()[0];
    $docComment = $method->getDocComment();

    if (!$docComment) {
        return null;
    }

    $docBlock = $docBlockFactory->create($docComment);
    $responseTag = $docBlock->getTagsByName('return')[0] ?? null;

    if (!$responseTag) {
        return null;
    }

    $typeString = (string)$responseTag;

    dump($typeString);
});
