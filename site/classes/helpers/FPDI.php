<?php
defined( '_JEXEC' ) or die( 'Restricted access' );

require_once(JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'librairies'.DS.'TCPDF'.DS.'vendor'.DS.'autoload.php');


class flexicontent_FPDI extends FPDI
{
	var $header_conf = array();
	var $footer_conf = array();
	var $all_pages_header_text = null;
	var $all_pages_footer_text = null;

	var $per_page_headers = array();
	var $per_page_footers = array();
	var $addPageNumbers = false;


	public function setHeaderConf($header_conf = array())
	{
		$this->header_conf = $header_conf;
	}
	public function setFooterConf($footer_conf = array())
	{
		$this->footer_conf = $footer_conf;
	}


	public function setAllPagesHeaderText($all_pages_header_text = null)
	{
		if ($all_pages_header_text !== null) $this->all_pages_header_text = $all_pages_header_text;
	}
	public function setAllPagesFooterText($all_pages_footer_text = null)
	{
		if ($all_pages_footer_text !== null) $this->all_pages_footer_text = $all_pages_footer_text;
	}


	public function Header()
	{
		if (!$this->print_header /*|| $this->page == 0*/)
		{
			return;
		}

		// Save current font values
		$font_family = $this->FontFamily;
		$font_style  = $this->FontStyle;
		$font_size   = $this->FontSizePt;


		// ***
		// *** Prepare for output: text color, font family, font style
		// ***

		// Set text color (RGB values: 0-255)
		$this->SetTextColor(0, 0, 0, false);

		// Set font
		$this->SetFont(
			!empty($this->header_conf['ffamily']) ? $this->header_conf['ffamily'] : null,//$this->header_font[0],
			!empty($this->header_conf['fstyle']) ? $this->header_conf['fstyle'] : null,//$this->header_font[0],
			!empty($this->header_conf['fsize']) ? $this->header_conf['fsize'] : null//$this->header_font[0]
		);

		// Set style for cell border
		$prevlinewidth = $this->GetLineWidth();
		if (!empty($this->header_conf['border_width']))  $this->SetLineWidth($this->header_conf['border_width']);
		if (!empty($this->header_conf['border_color']))  $this->SetDrawColor($this->header_conf['border_color'][0], $this->header_conf['border_color'][1], $this->header_conf['border_color'][2]);


		// ***
		// *** Add Header text
		// ***

		$border_type = !empty($this->header_conf['border_type']) ? $this->header_conf['border_type'] : 0;
		$text_align = !empty($this->header_conf['text_align']) ? $this->header_conf['text_align'] : 'C';
		$header_text = @ $this->per_page_headers[$this->page] ?: $this->all_pages_header_text;

		if ($header_text)
		{
			$this->Cell(0, 0, $header_text, $border_type, 1, $text_align);
		}


		// ***
		// *** Restore line width and font values
		// ***
		$this->SetLineWidth($prevlinewidth);
		$this->SetFont($font_family, $font_style, $font_size);
	}


	public function Footer()
	{
		if (!$this->print_footer /*|| $this->page == 0*/)
		{
			return;
		}


		// ***
		// *** Save initial and current values
		// ***

		// Store (on 1st method call) original header margins
		$this->original_lMargin = !isset($this->original_lMargin) ? $this->lMargin : $this->original_lMargin;
		$this->original_rMargin = !isset($this->original_rMargin) ? $this->rMargin : $this->original_rMargin;

		// Save current font values
		$font_family = $this->FontFamily;
		$font_style  = $this->FontStyle;
		$font_size   = $this->FontSizePt;


		// ***
		// *** Prepare for output: text color, font family, font style
		// ***

		// Set text color (RGB values: 0-255)
		$this->SetTextColor(0, 0, 0, false);

		// Set font
		$this->SetFont(
			!empty($this->footer_conf['ffamily']) ? $this->footer_conf['ffamily'] : null,//$this->footer_font[0],
			!empty($this->footer_conf['fstyle']) ? $this->footer_conf['fstyle'] : null,//$this->footer_font[0],
			!empty($this->footer_conf['fsize']) ? $this->footer_conf['fsize'] : null//$this->footer_font[0]
		);

		// Set style for cell border
		$prevlinewidth = $this->GetLineWidth();
		if (!empty($this->footer_conf['border_width']))  $this->SetLineWidth($this->footer_conf['border_width']);
		if (!empty($this->footer_conf['border_color']))  $this->SetDrawColor($this->footer_conf['border_color'][0], $this->footer_conf['border_color'][1], $this->footer_conf['border_color'][2]);


		// ***
		// *** Prepare for output: position and sizes
		// ***

		// Dealing with new page, reset header margins to original values
		$this->rMargin = $this->original_rMargin;
		$this->lMargin = $this->original_lMargin;

		// Calculate footer height
		$footer_height = round((K_CELL_HEIGHT_RATIO * $this->footer_font[2]) / $this->k, 2);

		// Get footer y position
		$footer_y = $this->h - $this->footer_margin - $footer_height;

		// Set current position for output, right for Left-To-Right and left for RTL (Right-To-Left)
		$this->rtl
			? $this->SetXY($this->original_rMargin, $footer_y)
			: $this->SetXY($this->original_lMargin, $footer_y);


		// ***
		// *** Print document barcode, if this was set
		// ***
		if ($this->barcode)
		{
			$this->Ln();
			$barcode_width = round(($this->w - $this->original_lMargin - $this->original_rMargin)/3); //max width
			$this->writeBarcode($this->GetX(), $footer_y + $line_width, $barcode_width, $footer_height - $line_width, "C128B", false, false, 2, $this->barcode);
		}


		// ***
		// *** Add page number, if this was enabled
		// ***
		if ($this->addPageNumbers)
		{
			// Create page number text
			$pagenumtxt = $this->l['w_page']." ".$this->PageNo().' / ' . $this->getAliasNbPages();

			// Print page number
			$this->SetY($footer_y);
			if ($this->rtl)
			{
				$this->SetX($this->original_rMargin);
				$this->Cell(0, $footer_height, $pagenumtxt, 'T', 0, 'L');
			}
			else
			{
				$this->SetX($this->original_lMargin);
				$this->Cell(0, $footer_height, $pagenumtxt, 'T', 0, 'R');
			}
		}


		// ***
		// *** Add Footer text
		// ***

		$border_type = !empty($this->footer_conf['border_type']) ? $this->footer_conf['border_type'] : 0;
		$text_align = !empty($this->footer_conf['text_align']) ? $this->footer_conf['text_align'] : 'C';
		$footer_text = @ $this->per_page_footers[$this->page] ?: $this->all_pages_footer_text;

		if ($footer_text)
		{
			$this->Cell(0, $footer_height, $footer_text, $border_type, 0, $text_align);
		}


		// ***
		// *** Restore line width and font values
		// ***
		$this->SetLineWidth($prevlinewidth);
		$this->SetFont($font_family, $font_style, $font_size);
	}
}
