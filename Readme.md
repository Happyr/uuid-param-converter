# UUID Param Converter

An excellent ParamConverter that works with SensioLabsExtraBundle.
 
It converter parameters of the following pattern: 
- `name`_uuid
- `name`Uuid
- uuid

## Install

```
composer require happyr/uuid-param-converter
```

```yaml
# app/config/happyr_param_converter.yml
services:
    Happyr\UuidParamConverter:
        arguments: ['@doctrine.registry']
        tags:
            - { name: request.param_converter, priority: 5, converter: uuid_converter }
```