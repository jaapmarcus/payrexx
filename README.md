# Payrexx

Fossbilling module for [Payrexx](https://www.payrexx.com/en/home/)

## Installation

### Extension directory

The easiest way to install this extension is by using the FOSSBilling extension directory.

### Manual installation

- Download the latest release from the extension directory
- Create a new folder named Mollie in the /library/Payment/Adapter directory of your FOSSBilling installation
- Extract the archive you've downloaded in the first step into the new directory 
- Go to the "Payment gateways" page in your admin panel (under the "System" menu in the navigation bar) and find Mollie in the "New payment gateway" tab
- Click the cog icon next to Mollie to install and configure Payrexx

## Configuration

When configuring Payrexx provide the instance name without `https://` and `.payrexx.com` for example if your instance is `https://myname.payrexx.com` myname is supposed to be used

API keys can be generated under: "Intergrations" -> Api & Plugins -> Add Api key

## Contributing

We love our contributors! Feel free to create a pull request if you want to help out.

Not a developer? No problem! You can also help us by reporting bugs, creating feature requests

## Licensing

This extension is licensed under the GPLv3 license. See the LICENSE file for more information.