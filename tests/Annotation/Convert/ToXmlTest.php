<?php

namespace KevinGH\Box\Annotation\Convert;

use DOMDocument;
use KevinGH\Box\Annotation\Convert\ToXml;
use KevinGH\Box\Annotation\TestTokens;
use KevinGH\Box\Annotation\Tokens;

class ToXmlTest extends TestTokens
{
    /**
     * @var ToXml
     */
    private $converter;

    public function getDocs()
    {
        $docs = array();
        $tokens = $this->getTokens();

        /**
         * @Annotation
         */
        $docs[] = array(
            array_shift($tokens),
            <<<DOC
<?xml version="1.0"?>
<annotations>
  <annotation name="Annotation"/>
</annotations>

DOC
        );

        /**
         * @Annotation()
         */
        $docs[] = array(
            array_shift($tokens),
            <<<DOC
<?xml version="1.0"?>
<annotations>
  <annotation name="Annotation"/>
</annotations>

DOC
        );

        /**
         * @Annotation ()
         */
        $docs[] = array(
            array_shift($tokens),
            <<<DOC
<?xml version="1.0"?>
<annotations>
  <annotation name="Annotation"/>
</annotations>

DOC
        );

        /**
         * @A
         * @B
         */
        $docs[] = array(
            array_shift($tokens),
            <<<DOC
<?xml version="1.0"?>
<annotations>
  <annotation name="A"/>
  <annotation name="B"/>
</annotations>

DOC
        );

        /**
         * @A()
         * @B()
         */
        $docs[] = array(
            array_shift($tokens),
            <<<DOC
<?xml version="1.0"?>
<annotations>
  <annotation name="A"/>
  <annotation name="B"/>
</annotations>

DOC
        );

        /**
         * @Namespaced\Annotation
         */
        $docs[] = array(
            array_shift($tokens),
            <<<DOC
<?xml version="1.0"?>
<annotations>
  <annotation name="Namespaced\\Annotation"/>
</annotations>

DOC
        );

        /**
         * @Namespaced\ Annotation
         */
        $docs[] = array(
            array_shift($tokens),
            <<<DOC
<?xml version="1.0"?>
<annotations>
  <annotation name="Namespaced\\Annotation"/>
</annotations>

DOC
        );

        /**
         * @Namespaced\Annotation()
         */
        $docs[] = array(
            array_shift($tokens),
            <<<DOC
<?xml version="1.0"?>
<annotations>
  <annotation name="Namespaced\\Annotation"/>
</annotations>

DOC
        );

        /**
         * @Annotation("string")
         */
        $docs[] = array(
            array_shift($tokens),
            <<<DOC
<?xml version="1.0"?>
<annotations>
  <annotation name="Annotation">
    <value type="string">string</value>
  </annotation>
</annotations>

DOC
        );

        /**
         * @Annotation(
         *     "string"
         * )
         */
        $docs[] = array(
            array_shift($tokens),
            <<<DOC
<?xml version="1.0"?>
<annotations>
  <annotation name="Annotation">
    <value type="string">string</value>
  </annotation>
</annotations>

DOC
        );

        /**
         * @Annotation(123, "string", 1.23, CONSTANT, false, true, null)
         */
        $docs[] = array(
            array_shift($tokens),
            <<<DOC
<?xml version="1.0"?>
<annotations>
  <annotation name="Annotation">
    <value type="integer">123</value>
    <value type="string">string</value>
    <value type="float">1.23</value>
    <value type="constant">CONSTANT</value>
    <value type="boolean">0</value>
    <value type="boolean">1</value>
    <value type="null"/>
  </annotation>
</annotations>

DOC
        );

        /**
         * @Annotation(constant, FALSE, TRUE, NULL)
         */
        $docs[] = array(
            array_shift($tokens),
            <<<DOC
<?xml version="1.0"?>
<annotations>
  <annotation name="Annotation">
    <value type="constant">constant</value>
    <value type="boolean">0</value>
    <value type="boolean">1</value>
    <value type="null"/>
  </annotation>
</annotations>

DOC
        );

        /**
         * @Annotation(key="value")
         */
        $docs[] = array(
            array_shift($tokens),
            <<<DOC
<?xml version="1.0"?>
<annotations>
  <annotation name="Annotation">
    <value key="key" type="string">value</value>
  </annotation>
</annotations>

DOC
        );

        /**
         * @Annotation(a="b", c="d")
         */
        $docs[] = array(
            array_shift($tokens),
            <<<DOC
<?xml version="1.0"?>
<annotations>
  <annotation name="Annotation">
    <value key="a" type="string">b</value>
    <value key="c" type="string">d</value>
  </annotation>
</annotations>

DOC
        );

        /**
         * @Annotation(
         *     a=123,
         *     b="string",
         *     c=1.23,
         *     d=CONSTANT,
         *     e=false,
         *     f=true,
         *     g=null
         * )
         */
        $docs[] = array(
            array_shift($tokens),
            <<<DOC
<?xml version="1.0"?>
<annotations>
  <annotation name="Annotation">
    <value key="a" type="integer">123</value>
    <value key="b" type="string">string</value>
    <value key="c" type="float">1.23</value>
    <value key="d" type="constant">CONSTANT</value>
    <value key="e" type="boolean">0</value>
    <value key="f" type="boolean">1</value>
    <value key="g" type="null"/>
  </annotation>
</annotations>

DOC
        );

        /**
         * @Annotation({})
         */
        $docs[] = array(
            array_shift($tokens),
            <<<DOC
<?xml version="1.0"?>
<annotations>
  <annotation name="Annotation">
    <values/>
  </annotation>
</annotations>

DOC
        );

        /**
         * @Annotation(key={})
         */
        $docs[] = array(
            array_shift($tokens),
            <<<DOC
<?xml version="1.0"?>
<annotations>
  <annotation name="Annotation">
    <values key="key"/>
  </annotation>
</annotations>

DOC
        );

        /**
         * @Annotation({"string"})
         */
        $docs[] = array(
            array_shift($tokens),
            <<<DOC
<?xml version="1.0"?>
<annotations>
  <annotation name="Annotation">
    <values>
      <value type="string">string</value>
    </values>
  </annotation>
</annotations>

DOC
        );

        /**
         * @Annotation(
         *     {
         *         "string"
         *     }
         * )
         */
        $docs[] = array(
            array_shift($tokens),
            <<<DOC
<?xml version="1.0"?>
<annotations>
  <annotation name="Annotation">
    <values>
      <value type="string">string</value>
    </values>
  </annotation>
</annotations>

DOC
        );

        /**
         * @Annotation({123, "string", 1.23, CONSTANT, false, true, null})
         */
        $docs[] = array(
            array_shift($tokens),
            <<<DOC
<?xml version="1.0"?>
<annotations>
  <annotation name="Annotation">
    <values>
      <value type="integer">123</value>
      <value type="string">string</value>
      <value type="float">1.23</value>
      <value type="constant">CONSTANT</value>
      <value type="boolean">0</value>
      <value type="boolean">1</value>
      <value type="null"/>
    </values>
  </annotation>
</annotations>

DOC
        );

        /**
         * @Annotation({key="value"})
         */
        $docs[] = array(
            array_shift($tokens),
            <<<DOC
<?xml version="1.0"?>
<annotations>
  <annotation name="Annotation">
    <values>
      <value key="key" type="string">value</value>
    </values>
  </annotation>
</annotations>

DOC
        );

        /**
         * @Annotation({"key"="value"})
         */
        $docs[] = array(
            array_shift($tokens),
            <<<DOC
<?xml version="1.0"?>
<annotations>
  <annotation name="Annotation">
    <values>
      <value key="key" type="string">value</value>
    </values>
  </annotation>
</annotations>

DOC
        );

        /**
         * @Annotation({a="b", c="d"})
         */
        $docs[] = array(
            array_shift($tokens),
            <<<DOC
<?xml version="1.0"?>
<annotations>
  <annotation name="Annotation">
    <values>
      <value key="a" type="string">b</value>
      <value key="c" type="string">d</value>
    </values>
  </annotation>
</annotations>

DOC
        );

        /**
         * @Annotation({a="b", "c"="d", 123="e"})
         */
        $docs[] = array(
            array_shift($tokens),
            <<<DOC
<?xml version="1.0"?>
<annotations>
  <annotation name="Annotation">
    <values>
      <value key="a" type="string">b</value>
      <value key="c" type="string">d</value>
      <value key="123" type="string">e</value>
    </values>
  </annotation>
</annotations>

DOC
        );

        /**
         * @Annotation({key={}})
         */
        $docs[] = array(
            array_shift($tokens),
            <<<DOC
<?xml version="1.0"?>
<annotations>
  <annotation name="Annotation">
    <values>
      <values key="key"/>
    </values>
  </annotation>
</annotations>

DOC
        );

        /**
         * @Annotation(a={b={}})
         */
        $docs[] = array(
            array_shift($tokens),
            <<<DOC
<?xml version="1.0"?>
<annotations>
  <annotation name="Annotation">
    <values key="a">
      <values key="b"/>
    </values>
  </annotation>
</annotations>

DOC
        );

        /**
         * @Annotation({key: {}})
         */
        $docs[] = array(
            array_shift($tokens),
            <<<DOC
<?xml version="1.0"?>
<annotations>
  <annotation name="Annotation">
    <values>
      <values key="key"/>
    </values>
  </annotation>
</annotations>

DOC
        );

        /**
         * @Annotation(a={b: {}})
         */
        $docs[] = array(
            array_shift($tokens),
            <<<DOC
<?xml version="1.0"?>
<annotations>
  <annotation name="Annotation">
    <values key="a">
      <values key="b"/>
    </values>
  </annotation>
</annotations>

DOC
        );

        /**
         * @Annotation({key: "value"})
         */
        $docs[] = array(
            array_shift($tokens),
            <<<DOC
<?xml version="1.0"?>
<annotations>
  <annotation name="Annotation">
    <values>
      <value key="key" type="string">value</value>
    </values>
  </annotation>
</annotations>

DOC
        );

        /**
         * @Annotation({"key": "value"})
         */
        $docs[] = array(
            array_shift($tokens),
            <<<DOC
<?xml version="1.0"?>
<annotations>
  <annotation name="Annotation">
    <values>
      <value key="key" type="string">value</value>
    </values>
  </annotation>
</annotations>

DOC
        );

        /**
         * @Annotation({a: "b", c: "d"})
         */
        $docs[] = array(
            array_shift($tokens),
            <<<DOC
<?xml version="1.0"?>
<annotations>
  <annotation name="Annotation">
    <values>
      <value key="a" type="string">b</value>
      <value key="c" type="string">d</value>
    </values>
  </annotation>
</annotations>

DOC
        );

        /**
         * @Annotation({a: "b", "c": "d", 123: "e"})
         */
        $docs[] = array(
            array_shift($tokens),
            <<<DOC
<?xml version="1.0"?>
<annotations>
  <annotation name="Annotation">
    <values>
      <value key="a" type="string">b</value>
      <value key="c" type="string">d</value>
      <value key="123" type="string">e</value>
    </values>
  </annotation>
</annotations>

DOC
        );

        /**
         * @Annotation(
         *     {
         *         "a",
         *         {
         *             {
         *                 "c"
         *             },
         *             "b"
         *         }
         *     }
         * )
         */
        $docs[] = array(
            array_shift($tokens),
            <<<DOC
<?xml version="1.0"?>
<annotations>
  <annotation name="Annotation">
    <values>
      <value type="string">a</value>
      <values>
        <values>
          <value type="string">c</value>
        </values>
        <value type="string">b</value>
      </values>
    </values>
  </annotation>
</annotations>

DOC
        );

        /**
         * @Annotation(@Nested)
         */
        $docs[] = array(
            array_shift($tokens),
            <<<DOC
<?xml version="1.0"?>
<annotations>
  <annotation name="Annotation">
    <annotation name="Nested"/>
  </annotation>
</annotations>

DOC
        );

        /**
         * @Annotation(@Nested())
         */
        $docs[] = array(
            array_shift($tokens),
            <<<DOC
<?xml version="1.0"?>
<annotations>
  <annotation name="Annotation">
    <annotation name="Nested"/>
  </annotation>
</annotations>

DOC
        );

        /**
         * @Annotation(@Nested, @Nested)
         */
        $docs[] = array(
            array_shift($tokens),
            <<<DOC
<?xml version="1.0"?>
<annotations>
  <annotation name="Annotation">
    <annotation name="Nested"/>
    <annotation name="Nested"/>
  </annotation>
</annotations>

DOC
        );

        /**
         * @Annotation(@Nested(), @Nested())
         */
        $docs[] = array(
            array_shift($tokens),
            <<<DOC
<?xml version="1.0"?>
<annotations>
  <annotation name="Annotation">
    <annotation name="Nested"/>
    <annotation name="Nested"/>
  </annotation>
</annotations>

DOC
        );

        /**
         * @Annotation(
         *     @Nested(),
         *     @Nested()
         * )
         */
        $docs[] = array(
            array_shift($tokens),
            <<<DOC
<?xml version="1.0"?>
<annotations>
  <annotation name="Annotation">
    <annotation name="Nested"/>
    <annotation name="Nested"/>
  </annotation>
</annotations>

DOC
        );

        /**
         * @Annotation(key=@Nested)
         */
        $docs[] = array(
            array_shift($tokens),
            <<<DOC
<?xml version="1.0"?>
<annotations>
  <annotation name="Annotation">
    <annotation key="key" name="Nested"/>
  </annotation>
</annotations>

DOC
        );

        /**
         * @Annotation(a=@Nested(),b=@Nested)
         */
        $docs[] = array(
            array_shift($tokens),
            <<<DOC
<?xml version="1.0"?>
<annotations>
  <annotation name="Annotation">
    <annotation key="a" name="Nested"/>
    <annotation key="b" name="Nested"/>
  </annotation>
</annotations>

DOC
        );

        /**
         * @Annotation({key=@Nested})
         */
        $docs[] = array(
            array_shift($tokens),
            <<<DOC
<?xml version="1.0"?>
<annotations>
  <annotation name="Annotation">
    <values>
      <annotation key="key" name="Nested"/>
    </values>
  </annotation>
</annotations>

DOC
        );

        /**
         * @Annotation({a=@Nested(),b=@Nested})
         */
        $docs[] = array(
            array_shift($tokens),
            <<<DOC
<?xml version="1.0"?>
<annotations>
  <annotation name="Annotation">
    <values>
      <annotation key="a" name="Nested"/>
      <annotation key="b" name="Nested"/>
    </values>
  </annotation>
</annotations>

DOC
        );

        /**
         * @Annotation(
         *     @Nested(
         *         {
         *             "a",
         *             {
         *                 {
         *                     "c"
         *                 },
         *                 "b"
         *             }
         *         }
         *     ),
         *     @Nested(
         *         {
         *             "d",
         *             {
         *                 {
         *                     "f",
         *                 },
         *                 "e"
         *             }
         *         }
         *     )
         * )
         */
        $docs[] = array(
            array_shift($tokens),
            <<<DOC
<?xml version="1.0"?>
<annotations>
  <annotation name="Annotation">
    <annotation name="Nested">
      <values>
        <value type="string">a</value>
        <values>
          <values>
            <value type="string">c</value>
          </values>
          <value type="string">b</value>
        </values>
      </values>
    </annotation>
    <annotation name="Nested">
      <values>
        <value type="string">d</value>
        <values>
          <values>
            <value type="string">f</value>
          </values>
          <value type="string">e</value>
        </values>
      </values>
    </annotation>
  </annotation>
</annotations>

DOC
        );

        return $docs;
    }

    /**
     * @dataProvider getDocs
     */
    public function testConvert($tokens, $doc)
    {
        $tokens = new Tokens($tokens);

        $this->assertEquals(
            $doc,
            $this->converter->convert($tokens)->saveXml()
        );
    }

    /**
     * @dataProvider getDocs
     */
    public function testValidate($tokens, $doc)
    {
        /** @noinspection PhpVoidFunctionResultUsedInspection */
        $this->assertNull(
            $this->converter->validate($doc)
        );

        $dom = new DOMDocument();
        $dom->loadXML($doc);

        /** @noinspection PhpVoidFunctionResultUsedInspection */
        $this->assertNull(
            ToXml::validate($dom)
        );
    }

    public function testValidateBadDoc()
    {
        $this->setExpectedException(
            'Herrera\\Annotations\\Exception\\InvalidXmlException',
            'StartTag: invalid element name in Entity'
        );

        ToXml::validate('<');
    }

    public function testValidateInvalidArg()
    {
        $this->setExpectedException(
            'Herrera\\Annotations\\Exception\\InvalidArgumentException',
            'The $input argument must be an instance of DOMDocument, integer given.'
        );

        ToXml::validate(123);
    }

    public function testValidateInvalidDoc()
    {
        $this->setExpectedException(
            'Herrera\\Annotations\\Exception\\InvalidXmlException',
            'The attribute \'key\' is not allowed.'
        );

        ToXml::validate('<annotations key="key"/>');
    }

    protected function setUp()
    {
        $this->converter = new ToXml();
    }
}
