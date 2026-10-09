define('jewellery:handlers/convert-actions', ['action-handler'], (Dep) => {

    /**
     * Detail-view actions: Enquiry → Deal, Accepted Quotation → Order.
     * The server re-checks every rule (status, permissions, duplicates); these
     * visibility checks only keep the UI tidy.
     */
    return class extends Dep {

        isEnquiryConvertible() {
            const model = this.view.model;

            return !['Converted', 'Lost'].includes(model.get('status')) &&
                this.view.getAcl().check(model, 'edit') &&
                this.view.getAcl().check('Opportunity', 'create');
        }

        isQuotationConvertible() {
            const model = this.view.model;

            return model.get('status') === 'Accepted' &&
                this.view.getAcl().check(model, 'edit') &&
                this.view.getAcl().check('CustomerOrder', 'create');
        }

        convertEnquiry() {
            this.convert(
                `Enquiry/${this.view.model.id}/convertToDeal`,
                'Convert this enquiry into a deal in the sales pipeline? The enquiry will be kept and marked Converted.',
                result => `#Opportunity/view/${result.id}`
            );
        }

        convertQuotation() {
            this.convert(
                `Quotation/${this.view.model.id}/convertToOrder`,
                'Create an order from this accepted quotation? Its line items will be copied and the quotation will be locked.',
                result => `#CustomerOrder/view/${result.id}`
            );
        }

        convert(url, message, getRoute) {
            this.view.confirm(message, () => {
                Espo.Ui.notifyWait();

                Espo.Ajax.postRequest(url)
                    .then(result => {
                        Espo.Ui.success('Done');
                        this.view.getRouter().navigate(getRoute(result), {trigger: true});
                    });
            });
        }
    };
});
