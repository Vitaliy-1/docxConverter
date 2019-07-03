<?php

/**
 * @file plugins/generic/docxConverter/classes/DOCXConverterDocument.inc.php
 *
 * Copyright (c) 2014-2019 Simon Fraser University Library
 * Copyright (c) 2003-2019 John Willinsky
 * Distributed under the GNU GPL v2.
 *
 * @brief extends the creator class for JATS XML
 */

require_once __DIR__ . "/../docxToJats/vendor/autoload.php";
use docx2jats\jats\Document;
use docx2jats\DOCXArchive;

class DOCXConverterDocument extends Document {

	protected $xpath;

	public function __construct(DOCXArchive $docxArchive)
	{
		parent::__construct($docxArchive);
		$this->xpath = new DOMXPath($this);
		$this->removeTableParagraphs();
	}

	public function setDocumentMeta(Request $reguest, Submission $submission) {

		// Delete all nodes if exist
		while($this->front->hasChildNodes()) {
			$this->front->removeChild($this->front->firstChild);
		}

		// Append nodes according to Texture specifications
		$articleMeta = $this->createElement("article-meta");
		$this->front->appendChild($articleMeta);

		$titleGroup = $this->createElement("title-group");
		$articleMeta->appendChild($titleGroup);

		$articleTitle = $this->createElement("article-title", htmlspecialchars($submission->getLocalizedTitle()));
		$titleGroup->appendChild($articleTitle);

		if ($submission->getLocalizedSubtitle()) {
			$subtitle = $this->createElement("subtitle", htmlspecialchars($submission->getLocalizedSubtitle()));
			$titleGroup->appendChild($subtitle);
		}

		if (!empty($submission->getAuthors())) {
			$contribGroup = $this->createElement("contrib-group");
			$contribGroup->setAttribute("content-type", "author");
			$articleMeta->appendChild($contribGroup);

			foreach ($submission->getAuthors() as $key => $author) {
				/* @var $author Author */
				$contrib = $this->createElement("contrib");
				$contrib->setAttribute("contrib-type", "person");
				$contribGroup->appendChild($contrib);

				$name = $this->createElement("name");
				$contrib->appendChild($name);

				if ($author->getLocalizedFamilyName()) {
					$surname = $this->createElement("surname", htmlspecialchars($author->getLocalizedFamilyName()));
					$name->appendChild($surname);
				}

				$givenNames = $this->createElement("given-names", htmlspecialchars($author->getLocalizedGivenName()));
				$name->appendChild($givenNames);

				if ($author->getEmail()) {
					$email = $this->createElement("email", htmlspecialchars($author->getEmail()));
					$contrib->appendChild($email);
				}

				$xref = $this->createElement("xref");
				$xref->setAttribute("ref-type", "aff");
				$xref->setAttribute("rid", "aff-" . ($key+1));
				$contrib->appendChild($xref);

				$aff = $this->createElement("aff");
				$aff->setAttribute("id", "aff-" . ($key+1));
				$articleMeta->appendChild($aff);

				$institution = $this->createElement("institution", htmlspecialchars($author->getLocalizedAffiliation()));
				$aff->appendChild($institution);

				$country = $this->createElement("country", htmlspecialchars($author->getCountryLocalized()));
				$aff->appendChild($country);
			}
		}

		$history = $this->createElement("history");
		$articleMeta->appendChild($history);

		$dateReceived = $this->createElement("date");
		$dateReceived->setAttribute("date-type", "received");
		$drf = new DateTime($submission->getDateSubmitted());
		$dateReceived->setAttribute("iso-8601-date", $drf->format("Y-m-d"));
		$history->appendChild($dateReceived);

		$dayReceived = $this->createElement("day", $drf->format("d"));
		$dateReceived->appendChild($dayReceived);
		$monthReceived = $this->createElement("month", $drf->format("m"));
		$dateReceived->appendChild($monthReceived);
		$yearReceived = $this->createElement("year", $drf->format("Y"));
		$dateReceived->appendChild($yearReceived);

		if ($submission->getDatePublished()) {
			$datePublished = $this->createElement("date");
			$datePublished->setAttribute("data-type", "published");
			$dpf = new DateTime($submission->getDatePublished());
			$datePublished->setAttribute("iso-8601-date", $dpf->format("Y-m-d"));
			$history->appendChild($datePublished);

			$dayPublished = $this->createElement("day", $dpf->format("d"));
			$datePublished->appendChild($dayPublished);
			$monthPublished = $this->createElement("month", $dpf->format("m"));
			$datePublished->appendChild($monthPublished);
			$yearPublished = $this->createElement("year", $dpf->format("Y"));
			$datePublished->appendChild($yearPublished);
		}

		// TODO convert abstract from HTML to JATS to be displayed by Texture
	}

	private function removeTableParagraphs() {
		$cellParagraphs = $this->xpath->query("//td/p|//th/p");
		foreach ($cellParagraphs as $cellParagraph) {
			$paragraphContent = $this->xpath->query("descendant::*|text()", $cellParagraph);
			foreach ($paragraphContent as $child) {
				$cellParagraph->parentNode->appendChild($child);
			}

			$cellParagraph->parentNode->removeChild($cellParagraph);
		}
	}
}
