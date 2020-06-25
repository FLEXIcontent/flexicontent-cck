<?php
defined( '_JEXEC' ) or die( 'Restricted access' );

require_once (JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'librairies'.DS.'PdfToText'.DS.'PdfToText.php');


class flexicontent_pdf_data
{
	var $pdf_string;
	
	function __construct()
	{
	}
	
	function getText()
	{
		return $this->pdf_string;
	}
}



class flexicontent_pdf
{
	function __construct()
	{
	}

	function parseFile($pdffile)
	{
		$pdf_data = new flexicontent_pdf_data();
		$parser = new PdfToText($pdffile);
		$pdf_data->pdf_string = $parser->Text;

		return $pdf_data;
	}	
}
