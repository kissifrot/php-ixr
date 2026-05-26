<?php

namespace IXR\tests\Message;

use IXR\Message\Message;
use PHPUnit\Framework\TestCase;

class MessageTest extends TestCase
{

    public function testUntypedValue(): void
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>
                <methodCall>
                    <methodName>wiki.getBackLinks</methodName>
                    <params>
                        <param>
                            <value> change  </value>
                        </param>
                    </params>
                </methodCall>';

        $ixrmsg = new Message($xml);
        $ixrmsg->parse();

        $this->assertEquals('methodCall', $ixrmsg->messageType);
        $this->assertEquals('wiki.getBackLinks', $ixrmsg->methodName);
        $this->assertEquals([' change  '], $ixrmsg->params);
    }

    public function testStringValue(): void
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>
                <methodCall>
                    <methodName>wiki.getBackLinks</methodName>
                    <params>
                        <param>
                            <value>
                                <string> change  </string>
                            </value>
                        </param>
                    </params>
                </methodCall>';

        $ixrmsg = new Message($xml);
        $ixrmsg->parse();

        $this->assertEquals('methodCall', $ixrmsg->messageType);
        $this->assertEquals('wiki.getBackLinks', $ixrmsg->methodName);
        $this->assertEquals([' change  '], $ixrmsg->params);
    }

    public function testEmptyValue(): void
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>
                <methodCall>
                    <methodName>wiki.getBackLinks</methodName>
                    <params>
                        <param>
                            <value>
                                <string></string>
                            </value>
                        </param>
                    </params>
                </methodCall>';

        $ixrmsg = new Message($xml);
        $ixrmsg->parse();

        $this->assertEquals('methodCall', $ixrmsg->messageType);
        $this->assertEquals('wiki.getBackLinks', $ixrmsg->methodName);
        $this->assertEquals([''], $ixrmsg->params);
    }

    public function testStruct(): void
    {
        $xml = '<?xml version=\'1.0\'?>
                <methodCall>
                    <methodName>wiki.putPage</methodName>
                    <params>
                        <param>
                            <value><string>start</string></value>
                        </param>
                        <param>
                            <value><string>test text   </string></value>
                        </param>
                        <param>
                            <value><struct>
                                <member>
                                    <name>sum</name>
                                    <value><string>xmlrpc edit</string></value>
                                </member>
                                <member>
                                    <name>minor</name>
                                    <value><string>1</string></value>
                                </member>
                            </struct></value>
                        </param>
                    </params>
                </methodCall>';

        $ixrmsg = new Message($xml);
        $ixrmsg->parse();

        $this->assertEquals('methodCall', $ixrmsg->messageType);
        $this->assertEquals('wiki.putPage', $ixrmsg->methodName);
        $this->assertEquals(['start', 'test text   ', ['sum' => 'xmlrpc edit', 'minor' => '1']], $ixrmsg->params);
    }
}
