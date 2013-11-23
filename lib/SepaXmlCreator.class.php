<?php
/*
 * Copyright (c) 2013 Thomas Schiffler (http://www.ThomasSchiffler.de)
 * GPL (http://www.opensource.org/licenses/gpl-license.php) license.
 *
 * Korbinian Pauli
 */

namespace SepaXmlCreator;

class SepaTransaction {
	var $end2end, $iban, $bic, $recipient, $reference, $amount;

	public function setEnd2End($end2end) {
		$this->end2end = $end2end;
	}

	public function setIban($iban) {
		$this->iban = str_replace(' ','',$iban);
	}

	public function setBic($bic) {
		$this->bic = $bic;
	}

	public function setRecipient($recipient) {
		$this->recipient = $recipient;
	}

	public function setReference($reference) {
		$this->reference = $reference;
	}

	function setAmount($amount) {
		$this->amount = $amount;
	}
}

class SepaXmlCreator {
	var $transactions = array();

	var $debitorName, $debitorIban, $debitorBic;
	var $offset = 0;
	var $currency = "EUR";

	public function setDebitorValues($name, $iban, $bic) {
		$this->debitorName = $name;
		$this->debitorIban = $iban;
		$this->debitorBic = $bic;
	}

	public function setCurrency($currency) {
		$this->currency = $currency;
	}

	public function addTransaction($transaction) {
		array_push($this->transactions, $transaction);
	}

	function setExecutionOffset($offset) {
		$this->offset = $offset;
	}

	function generateTransferFile() {
		$dom = new \DOMDocument('1.0', 'utf-8');

		// Build Document-Root
		$document = $dom->createElement('Document');
		$document->setAttribute('xmlns', 'urn:iso:std:iso:20022:tech:xsd:pain.001.002.03');
		$document->setAttribute('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
		$document->setAttribute('xsi:schemaLocation', 'urn:iso:std:iso:20022:tech:xsd:pain.001.002.03 pain.001.002.03.xsd');
		$dom->appendChild($document);

		// Build Content-Root
		$content = $dom->createElement('CstmrCdtTrfInitn');
		$document->appendChild($content);

		// Build Header
		$header = $dom->createElement('GrpHdr');
		$content->appendChild($header);

		$creationTime = time();

		// Msg-ID
		$header->appendChild($dom->createElement('MsgId', $this->debitorBic . '00' . date('YmdHis', $creationTime)));
		$header->appendChild($dom->createElement('CreDtTm', date('Y-m-d', $creationTime) . 'T' . date('H:i:s', $creationTime) . '.000Z'));
		$header->appendChild($dom->createElement('NbOfTxs', count($this->transactions)));
		$header->appendChild($initatorName = $dom->createElement('InitgPty'));
		$initatorName->appendChild($dom->createElement('Nm', $this->debitorName));

		// PaymentInfo
		$paymentInfo = $dom->createElement('PmtInf');
		$content->appendChild($paymentInfo);

		$paymentInfo->appendChild($dom->createElement('PmtInfId', 'PMT-ID0-' . date('YmdHis', $creationTime)));
		// TRF = Transfer (Überweisung), TRA = CreditTransfer (Lastschrift)
		$paymentInfo->appendChild($dom->createElement('PmtMtd', 'TRF'));
		$paymentInfo->appendChild($dom->createElement('BtchBookg', 'true'));
		$paymentInfo->appendChild($dom->createElement('NbOfTxs', count($this->transactions)));
		$paymentInfo->appendChild($dom->createElement('CtrlSum', $this->getTotalAmount()));
		$paymentInfo->appendChild($tmp1 = $dom->createElement('PmtTpInf'));
		$tmp1->appendChild($tmp2 = $dom->createElement('SvcLvl'));
		$tmp2->appendChild($dom->createElement('Cd', 'SEPA'));

		// calculation execution date
		$executionTimestamp = $creationTime;
		if ($this->offset > 0) {
			$executionTimestamp += 24 * 3600 * $this->offset;
		}
		$paymentInfo->appendChild($dom->createElement('ReqdExctnDt', date('Y-m-d', $executionTimestamp)));

		// Debitor data
		$paymentInfo->appendChild($tmp1 = $dom->createElement('Dbtr'));
		$tmp1->appendChild($dom->createElement('Nm', $this->debitorName));
		$paymentInfo->appendChild($tmp1 = $dom->createElement('DbtrAcct'));
		$tmp1->appendChild($tmp2 = $dom->createElement('Id'));
		$tmp2->appendChild($dom->createElement('IBAN', $this->debitorIban));
		$paymentInfo->appendChild($tmp1 = $dom->createElement('DbtrAgt'));
		$tmp1->appendChild($tmp2 = $dom->createElement('FinInstnId'));
		$tmp2->appendChild($dom->createElement('BIC', $this->debitorBic));

		$paymentInfo->appendChild($dom->createElement('ChrgBr', 'SLEV'));

		// Add transactions
		foreach ($this->transactions as $transaction) {
			$paymentInfo->appendChild($transactionElement = $dom->createElement('CdtTrfTxInf'));

			// End2End setzen
			if (isset($transaction->end2end)) {
				$transactionElement->appendChild($tmp1 = $dom->createElement('PmtId'));
				$tmp1->appendChild($dom->createElement('EndToEndId', $transaction->end2end));
			}

			// Amount
			$transactionElement->appendChild($tmp1 = $dom->createElement('Amt'));
			$tmp1->appendChild($tmp2 = $dom->createElement('InstdAmt', $transaction->amount));
			$tmp2->setAttribute('Ccy', $this->currency);

			// Institut
			$transactionElement->appendChild($tmp1 = $dom->createElement('CdtrAgt'));
			$tmp1->appendChild($tmp2 = $dom->createElement('FinInstnId'));
			$tmp2->appendChild($dom->createElement('BIC', $transaction->bic));

			// recipient
			$transactionElement->appendChild($tmp1 = $dom->createElement('Cdtr'));
			$tmp1->appendChild($dom->createElement('Nm', $transaction->recipient));

			// IBAN
			$transactionElement->appendChild($tmp1 = $dom->createElement('CdtrAcct'));
			$tmp1->appendChild($tmp2 = $dom->createElement('Id'));
			$tmp2->appendChild($dom->createElement('IBAN', $transaction->iban));

			// reference
			$transactionElement->appendChild($tmp1 = $dom->createElement('RmtInf'));
			$tmp1->appendChild($dom->createElement('Ustrd', $transaction->reference));
		}

		// export XML
		return $dom->saveXML();
	}

	private function getTotalAmount() {
		$amount = 0;

		foreach ($this->transactions as $transaction) {
			$amount += $transaction->amount;
		}

		return $amount;
	}
}