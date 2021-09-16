<?php

namespace IXR\tests\Message;

use IXR\Message\Message;
use PHPUnit\Framework\TestCase;

class ixr_library_ixr_message_test extends TestCase
{

    function testUntypedValue()
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

        $this->assertEquals($ixrmsg->messageType, 'methodCall');
        $this->assertEquals($ixrmsg->methodName, 'wiki.getBackLinks');
        $this->assertEquals($ixrmsg->params, [' change  ']);
    }

    function testStringValue()
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

        $this->assertEquals($ixrmsg->messageType, 'methodCall');
        $this->assertEquals($ixrmsg->methodName, 'wiki.getBackLinks');
        $this->assertEquals($ixrmsg->params, [' change  ']);
    }

    function testEmptyValue()
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

        $this->assertEquals($ixrmsg->messageType, 'methodCall');
        $this->assertEquals($ixrmsg->methodName, 'wiki.getBackLinks');
        $this->assertEquals($ixrmsg->params, ['']);
    }

    function testStruct()
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

        $this->assertEquals($ixrmsg->messageType, 'methodCall');
        $this->assertEquals($ixrmsg->methodName, 'wiki.putPage');
        $this->assertEquals($ixrmsg->params, ['start', 'test text   ', ['sum' => 'xmlrpc edit', 'minor' => '1']]);
    }

}
