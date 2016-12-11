<?php

include_once WPISPCONFIG3_PLUGIN_DIR . 'pdf-php-experimental/src/CpdfExtension.php';

use ROSPDF\Cpdf as Cpdf;
use ROSPDF\CpdfExtension as Cpdf_Extension;
use ROSPDF\CpdfLineStyle as Cpdf_LineStyle;
use ROSPDF\CpdfTable as Cpdf_Table;

class IspconfigInvoicePdf { 
    public static function init() {
        return new self();
    }

    public function __construct(){

    }

    /**
     * Used to build a pdf invoice using the WC_Order object
     * @param {WC_Order} $order - the woocommerce order object
     * @param {Array} $invoice-> list of extra data passed as array (E.g. invoice_number, created, due date, ...)
     */
    public function BuildInvoice($invoice, $isOffer = false, $stream = false){
        setlocale(LC_ALL, get_locale());
        $order = $invoice->order;

        $items = $order->get_items();

        $billing_info = str_replace('<br/>', "\n", $order->get_formatted_billing_address());
                    
        Cpdf::$DEBUGLEVEL = Cpdf::DEBUG_MSG_ERR;
        //Cpdf::$DEBUGLEVEL = Cpdf::DEBUG_ALL;
                
        $pdf = new Cpdf_Extension(Cpdf::$Layout['A4']);
        $pdf->Compression = 0;
        //$pdf->ImportPage(1);
        if($isOffer) {
            $headlineText =  __('Offer', 'wp-ispconfig3') . ' ' . $invoice->offer_number;
        } else {
            $headlineText =  __('Invoice', 'wp-ispconfig3') . ' ' . $invoice->invoice_number;
        }
            
        $pdf->Metadata->SetInfo('Title', sprintf(WPISPConfig3::$OPTIONS['wc_pdf_title'], $headlineText) );
        
        $ls = new Cpdf_LineStyle(1, 'butt', 'miter');
        
        // Logo
        if(file_exists(WPISPCONFIG3_PLUGIN_DIR . '/' . WPISPConfig3::$OPTIONS['wc_pdf_logo'])) {
            $logo = $pdf->NewAppearance();
            $logo->AddImage('right',790, WPISPCONFIG3_PLUGIN_DIR . '/' . WPISPConfig3::$OPTIONS['wc_pdf_logo'], 280);
        }
                
        // billing info
        $billing_text = $pdf->NewAppearance(['uy'=> 650, 'addlx' => 20, 'ly' => 520, 'ux'=> 300]);
        if(!empty(WPISPConfig3::$OPTIONS['wc_pdf_addressline'])) {
            $billing_text->SetFont('Helvetica', 8);
            $billing_text->AddText( "<strong>" . WPISPConfig3::$OPTIONS['wc_pdf_addressline'] . "</strong>\n");
            $billing_text->AddLine(0, -11, 177, 0, $ls);
        }
        
        $billing_text->SetFont('Helvetica', 10);
        $billing_text->AddText($billing_info);
        
        // Rechnung info
        $billing_text = $pdf->NewAppearance(['uy'=> 650, 'lx' => 400, 'ly' => 520, 'addux' => -20]);
        $billing_text->SetFont('Helvetica', 10);
        $billing_text->AddText(sprintf( WPISPConfig3::$OPTIONS['wc_pdf_info'], strftime('%x',strtotime($invoice->created))) );
        
        if($order->_paid_date && !$isOffer) {
            $billing_text->AddColor(1,0,0);
            $billing_text->SetFont('Helvetica', 12);
            $billing_text->AddText("\n" . sprintf(__('Paid at', 'wp-ispconfig3') . ' %s', strftime('%x',strtotime($order->_paid_date)) ) );
        }

        // Zahlungsinfo und AGB
        $payment_text = $pdf->NewAppearance(['uy'=> 130, 'addlx' => 20, 'addux' => -20]);
        $payment_text->SetFont('Helvetica', 8);
        $payment_text->AddText("<strong>" .  WPISPConfig3::$OPTIONS['wc_pdf_condition'] . "</strong>",0, 'center');

        // Firmeninfo (1)
        $billing_text = $pdf->NewAppearance(['uy'=> 100, 'addlx' => 20, 'ux' => 200]);
        $billing_text->SetFont('Helvetica', 8);
        $billing_text->AddText( WPISPConfig3::$OPTIONS['wc_pdf_block1'] );
        // firmeninfo (2)
        $billing_text = $pdf->NewAppearance(['uy'=> 100, 'lx' => 200, 'ux' => 370]);
        $billing_text->SetFont('Helvetica', 8);
        $billing_text->AddText( WPISPConfig3::$OPTIONS['wc_pdf_block2']);
        // firmeninfo (3)
        $billing_text = $pdf->NewAppearance(['uy'=> 100, 'lx' => 430, 'addux' => -20]);
        $billing_text->SetFont('Helvetica', 8);
        $billing_text->AddText(WPISPConfig3::$OPTIONS['wc_pdf_block3']);
        
        // Rechnungsnummer
        $text = $pdf->NewText(['uy' => 510, 'ly' => 490, 'addlx' => 20, 'addux' => -20]);
        $text->SetFont('Helvetica', 15);
        $text->AddText("$headlineText");
        
        $table = $pdf->NewTable(array('uy'=>480, 'addlx' => 20, 'addux' => -20,'ly' => 120), 4, null, $ls, Cpdf_Table::DRAWLINE_HEADERROW);
        
        $table->SetColumnWidths(30,240);
        $table->Fit = true;

        $table->AddCell("<strong>". __('No#', 'wp-ispconfig3') ."</strong>");
        $table->AddCell("<strong>". __('Description', 'wp-ispconfig3') ."</strong>");
        $table->AddCell("<strong>". __('Qty', 'wp-ispconfig3') ."</strong>", 'right');
        $table->AddCell("<strong>". __('Net', 'wp-ispconfig3') ."</strong>", 'right');
            
        $i = 1;
        $summary = 0;
        $summaryTax = 0;
        
        
        // add the fees to positions
        if($invoice->isFirst)
            $items = array_merge($items, $order->get_fees());

        foreach($items as $v){
            $product = null;
            // check if product id is available and fetch the ISPCONFIG tempalte ID
            if(!empty($v['product_id']))
                $product = wc_get_product($v['product_id']);
                
            if(!isset($v['qty'])) $v['qty'] = 1;

            if($product instanceof WC_Product_Webspace) {
                // if its an ISPCONFIG Template product
                $current = new DateTime($invoice->created);
                $next = clone $current;
                if($v['qty'] == 1) {
                    $next->add(new DateInterval('P1M'));
                } else if($v['qty'] == 12) {
                    // overwrite the QTY to be 1 MONTH
                    $next->add(new DateInterval('P12M'));
                }
                $qtyStr = number_format($v['qty'], 0, ',',' ') . ' Monat(e)';
                if(!$isOffer)
                    $v['name'] .= "\n<strong>Zeitraum: " . $current->format('d.m.Y')." - ".$next->format('d.m.Y')."</strong>\n";
            } else if($product instanceof WC_Product_Hour) {
                // check if product type is "hour" to output hours instead of Qty
                $qtyStr = number_format($v['qty'], 1, ',',' ');
			    $qtyStr .= ' Std.';
			} else {
			    $qtyStr = number_format($v['qty'], 2, ',',' ');
			}
            
            $total = round($v['line_total'], 2);
            $tax = round($v['line_tax'], 2);

            $table->AddCell("$i", null, [], ['top' => 5]);
            $table->AddCell($v['name'], null, [], ['top' => 5]);
            $table->AddCell($qtyStr, 'right', [], ['top' => 5]);
            $table->AddCell(number_format($total, 2, ',',' ') . ' ' . $order->get_order_currency(), 'right', [], ['top' => 5]);
            
            $summary += $total;
            $summaryTax += $tax;

            if(!empty($v['hint']))
            {
                $table->AddCell('');
                $table->AddCell($v['hint']);
                $table->AddCell('');
                $table->AddCell('');
            }

            $i++;
        }

        $table->AddCell("", null, [], ['top' => 5]);
        $table->AddCell("", null, [], ['top' => 5]);
        $table->AddCell("<strong>".__('Summary', 'wp-ispconfig3')."</strong>", 'right', [], ['top' => 15]);
        $table->AddCell("<strong>".number_format($summary, 2,',',' '). ' ' . $order->get_order_currency()."</strong>", 'right', [], ['top' => 15]);

        $table->AddCell("", null, [], ['top' => 5]);
        $table->AddCell("", null, [], ['top' => 5]);
        $table->AddCell("<strong>+ 19% ".__('Tax', 'wp-ispconfig3')."</strong>", 'right', [], ['top' => 5]);
        $table->AddCell("<strong>".number_format($summaryTax, 2,',',' '). ' ' . $order->get_order_currency() ."</strong>", 'right', [], ['top' => 5]);
        
        $table->AddCell("", null, [], ['top' => 5]);
        $table->AddCell("", null, [], ['top' => 5]);
        $table->AddCell("<strong>".__('Total', 'wp-ispconfig3')."</strong>", 'right', [], ['top' => 15]);
        $table->AddCell("<strong>".number_format($summary + $summaryTax, 2,',',' '). ' ' . $order->get_order_currency()."</strong>", 'right', [], ['top' => 15]);
        
        $table->EndTable();
        
        if($stream)
        {
            $pdf->Stream($invoice->invoice_number.'.pdf');
            return;
        }
        return $pdf->OutputAll();
    }

    /**
     * Used to trigger on specified parameters
     */
    public function Trigger(){
        global $wpdb, $pagenow, $current_user;

        // skip invoice output when no invoice id is defined (and continue with the default page call)
        if(empty($_GET['invoice'])) return;
        // check if woocommerce is installed
        if(!IspconfigWcBackend::IsWooCommerceAvailable()) {
            error_log("ISPConfig WC Backend: Invoice parameter shipped, but no woocommerce available");
            return;
        }

        $invoice = new IspconfigInvoice((integer)$_GET['invoice']);
        if(!$invoice->ID) die("Invoice not found");

        // invoice has been defined but user does not have the cap to display it
        if($invoice->customer_id != $current_user->ID && !current_user_can('ispconfig_invoice')) die("You are not allowed to view invoices: Cap 'ispconfig_invoice' not set");
        
        if(isset($_GET['preview'])) {
            //$order = new WC_Order($res['wc_order_id']);
            
            echo $this->BuildInvoice($invoice, true,true);
        } else {
            header("Content-type: application/pdf");
            header("Content-Disposition: inline; filename=".$invoice->invoice_number .'.pdf');

            echo $invoice->document;
        }
        die;
    }
}