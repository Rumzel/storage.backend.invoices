<?php

namespace App\Entity;

abstract class InvoicePayerInterface
{
    abstract public function getEmail(): ?string;
    
    abstract public function getName(): string;

    abstract public function getAddressFirst(): ?string;

    abstract public function getAddressSecond(): ?string;

    abstract public function getPaymentRegistrationNumber(): ?string;

    abstract public function getPaymentTaxNumber(): ?string;

    abstract public function getPaymentAdditionalLines(): ?string;

    function tplVars(): array
    {
        return [
            'name' => $this->getName(),
            'subName' => null,
            'infoLines' => $this->getInfoLines()
        ];
    }

    /**
     * @return array
     */
    public function getInfoLines(): array
    {
        $lines = [];

        if (!empty($this->getAddressLines())) {
            $lines[] = $this->getAddressLines();
        }

        if (!empty($this->getRegAndTaxLines())) {
            $lines[] = $this->getRegAndTaxLines();
        }

        if (!empty($this->getExplodedPaymentAdditionalLines())) {
            $lines[] = $this->getExplodedPaymentAdditionalLines();
        }

        return $lines;
    }
    
    /**
     * @return string[]
     */
    public function getAddressLines(): array
    {
        $addressLines = [];
        
        if (!empty($this->getAddressFirst())) {
            $addressLines[] = $this->getAddressFirst();
        }
            
        if (!empty($this->getAddressSecond())) {
            $addressLines[] = $this->getAddressSecond();
        }
            
        return $addressLines;
    }

    /**
     * @return string[]
     */
    public function getRegAndTaxLines(): array
    {
//        $taxLabel = 'PVN nr.: ';
//        $regLabel = 'Reģ. nr.: ';
        $taxLabel = 'VAT No.: ';
        $regLabel = 'Reg. No.: ';
        
        $lines = [];
        
        if (!empty($this->getPaymentRegistrationNumber())) {
            $lines[] = $regLabel . $this->getPaymentRegistrationNumber();
        }
        
        if (!empty($this->getPaymentTaxNumber())) {
            $lines[] = $taxLabel . $this->getPaymentTaxNumber();
        }
        
        return $lines;
    }

    /**
     * @return string[]
     */
    public function getExplodedPaymentAdditionalLines(): array
    {
        return explode(PHP_EOL, $this->getPaymentAdditionalLines() ?? "");
    }
}