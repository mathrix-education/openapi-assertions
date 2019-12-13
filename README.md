# mathrix-education/openapi-assertions

![version]
![license]
![php-version]

[version]: https://img.shields.io/packagist/v/mathrix-education/openapi-assertions?style=flat-square
[license]: https://img.shields.io/packagist/l/mathrix-education/openapi-assertions?style=flat-square
[php-version]: https://img.shields.io/packagist/php-v/mathrix-education/openapi-assertions?style=flat-square

Base library of all Mathrix Education SA PHP projects. 

Allow to test API responses against an OpenAPI v3 specification. Proudly
maintained by Mathieu Bour <mathieu@mathrix.fr>, Vice-CTO.

The library heavily relies on
[league/openapi-psr7-validator](https://github.com/thephpleague/openapi-psr7-validator)
formerly
[lezhnev74/openapi-psr7-validator](https://github.com/lezhnev74/openapi-psr7-validator).

## Lumen
In order to use OpenAPI assertions with lumen, you need to install symfony/psr-http-message-bridge and nyholm/psr7. You can do it with:

```bash
composer require --dev nyholm/psr7 symfony/psr-http-message-bridge
```

Then, add the LumenOpenAPIAssertions trait to your base TestCase, like
so:

```php
use \Mathrix\OpenAPI\Assertions\Lumen\LumenOpenAPIAssertions;

class TestCase extends LumenTestCase {
    use LumenOpenAPIAssertions;
    
    public static function setupBeforeClass()
    {
        self::$openAPISpecificationPath = PATH_TO_SPEC_YAML;
        self::bootLumenOpenAPIAssertions();
    }
}
```

### Usage
To test that an Illuminate Response matches the specification, simply
run:

```php
class TestBar extends TesCase {
    public function testFoo() {
        // Your test code
        $this->assertOpenAPIResponse($response); // Where response extends \Illuminate\Http\Response
    }
}
```

