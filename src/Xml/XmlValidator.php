<?php

namespace Pomocnik\Eet\Xml;

use DOMDocument;
use Pomocnik\Eet\Exceptions\XmlValidationException;

class XmlValidator
{
    private const XSD_PATH = __DIR__.'/../../resources/schema/EETXMLSchema.xsd';

    /**
     * Validuje Trzba XML proti XSD schematu.
     */
    public function validateTrzba(string $xml): bool
    {
        $xsdPath = config('eet.xsd_path', self::XSD_PATH);

        if (! file_exists($xsdPath)) {
            // Pokud XSD neni k dispozici, preskocime validaci
            return true;
        }

        $doc = new DOMDocument();

        if (! $doc->loadXML($xml)) {
            throw new XmlValidationException('Invalid XML: cannot parse document');
        }

        $doc->schemaValidate($xsdPath);

        // schemaValidate vraci true nebo vyhodi warning/error
        return true;
    }

    /**
     * Validuje XML odpoved (Odpoved) ze serveru.
     */
    public function validateOdpoved(string $xml): bool
    {
        $xsdPath = config('eet.xsd_path', self::XSD_PATH);

        if (! file_exists($xsdPath)) {
            return true;
        }

        $doc = new DOMDocument();

        if (! $doc->loadXML($xml)) {
            throw new XmlValidationException('Invalid response XML: cannot parse document');
        }

        $doc->schemaValidate($xsdPath);

        return true;
    }
}
