<?php
/**
 * Copyright 2023 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

require_once __DIR__ . "/vendor/autoload.php";

use \Payrexx\Payrexx;

class Payment_Adapter_Payrexx extends Payment_AdapterAbstract implements \FOSSBilling\InjectionAwareInterface
{
    protected array $config = [];
    //protected MollieApiClient $mollie;
    protected $di;

    public function setDi(\Pimple\Container|null $di): void
    {
        $this->di = $di;
    }

    public function getDi(): ?\Pimple\Container
    {
        return $this->di;
    }

    public function __construct(array $config)
    {
        $this->config = $config;
        $requiredParameters = ['api_key' => 'API Key', 'name' => "Instance name"];

        foreach ($requiredParameters as $requiredParameter=> $name) {
            if (empty($this->config[$requiredParameter])) {
                throw new Payment_Exception('The ":pay_gateway" payment gateway is not fully configured. Please configure the :missing', [':pay_gateway' => 'Payrexx', ':missing' => $name]);
            }
        }
    }

    public static function getConfig(): array
    {
        return [
            'supports_one_time_payments' => true,
            'supports_subscriptions' => false,
            'description' => 'Process payments via Payrexx',
            'logo' => [
                'logo' => '/Payrexx/Payrexx.png',
                'height' => '38px',
                'width' => '88px',
            ],
            'form' => [
                'name' => [
                    'text',
                    [
                        'label' => "Instance name (https://<name>.payrexx.com)",
                        'validators' => ['text'],
                    ]
                ],
                'api_key' => [
                    'text',
                    [
                        'label' => 'API key',
                        'validators' => ['text'],
                    ],
                ],
            ],
        ];
    }

    public function getHtml($api_admin, $invoice_id, $subscription): string
    {
        $invoice = $this->di['db']->load('Invoice', $invoice_id);
        return $this->_generateForm($invoice);
    }

    protected function _generateForm(Model_Invoice $invoice): string
    {
        $invoiceService = $this->di['mod_service']('Invoice');
        $payGateway = $this->di['db']->findOne('PayGateway', 'gateway = "Payrexx"');
        
        $instanceName = $this->config['name'];
        $api_key = $this->config['api_key'];
        $payrexx = new \Payrexx\Payrexx($instanceName, $api_key);
        
        $pinvoice = new \Payrexx\Models\Request\Invoice();
        $pinvoice->setReferenceId($invoice->id);
        $pinvoice->setTitle($this->getInvoiceTitle($invoice));
        $pinvoice->setPurpose($this->getInvoiceTitle($invoice));
        $pinvoice->setAmount($this->getAmountInCents($invoice));
        $pinvoice->setCurrency($invoice->currency);
        $pinvoice->setName($this->getInvoiceTitle($invoice));
        $pinvoice->setSuccessRedirectUrl($this->config['thankyou_url']);
        $pinvoice->setfailedRedirectUrl($this->config['thankyou_url']);
        
        try {
            $response = $payrexx->create($pinvoice); 
            
            $service = $this->di['mod_service']('invoice', 'transaction');
            $output = $service->create(array('txn_id' => $response -> getId(), 'bb_invoice_id' => $invoice->id, 'bb_gateway_id' => $payGateway->id));
            
            // We still need to update the unique ID
            $tx = $this->di['db']->getExistingModelById('Transaction', $output);
            $tx->txn_status = 'pending';
            $tx->amount = $invoiceService->getTotalWithTax($invoice);
            $tx->currency = $invoice->currency;
            $uniqid = uniqid();
            $tx->s_id = $uniqid;
            $this->di['db']->store($tx);

            return '<script type="text/javascript">window.location = "'. $response->getLink() . '";</script>';
        } catch (\Payrexx\PayrexxException $e) {
            print $e->getMessage();
        }
    }

    public function processTransaction($api_admin, $id, $data, $gateway_id): void
    {
        //10544027
        $trans_txn_id = $data['post']['transaction']['invoice']['paymentRequestId'];
        error_log($trans_txn_id);
        $transid = $this->di['db']->getCell('SELECT id from transaction WHERE txn_id = :txn_id', array(':txn_id' => $trans_txn_id));
        $tx = $this->di['db']->getExistingModelById('Transaction', $transid);
        $invoice = $this->di['db']->getExistingModelById('Invoice', $tx->invoice_id);
        $invoiceService = $this->di['mod_service']('Invoice');
        
        
        $client = $this->di['db']->getExistingModelById('Client', $invoice->client_id);
        $clientService = $this->di['mod_service']('client');
        
        $tx->txn_status = 'approved';
        $tx->status = 'processed';
        $tx->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($tx);
        
        $clientService->addFunds($client, $tx->amount, 'Payment with Mollie', array('status' => 'approved', 'invoice' => $tx->invoice_id));
        
        if ($tx->invoice_id) {
            $invoiceService->payInvoiceWithCredits($invoice);
        }
        
        $invoiceService->doBatchPayWithCredits(array('client_id' => $client->id));
        
    }
    
    public function getAmountInCents(\Model_Invoice $invoice)
    {
        $invoiceService = $this->di['mod_service']('Invoice');
        return $invoiceService->getTotalWithTax($invoice) * 100;
    }

    public function getInvoiceTitle(\Model_Invoice $invoice): string
    {
        $invoiceItems = $this->di['db']->getAll('SELECT title from invoice_item WHERE invoice_id = :invoice_id', array(':invoice_id' => $invoice->id));

        $params = array(
            ':id' => sprintf('%05s', $invoice->nr),
            ':serie' => $invoice->serie,
            ':title' => $invoiceItems[0]['title']
        );
        $title = __trans('Payment for invoice :serie:id [:title]', $params);
        
        if (count($invoiceItems) > 1) {
            $title = __trans('Payment for invoice :serie:id', $params);
        }
        
        return $title;
    }
}