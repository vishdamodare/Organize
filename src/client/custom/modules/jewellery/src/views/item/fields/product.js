define('jewellery:views/item/fields/product', ['views/fields/link'], (Dep) => {

    /**
     * Picking a catalogue product pre-fills the line description and the
     * indicative price. Both stay editable: the quoted price is the user's call.
     */
    return class extends Dep {

        selectPrimaryFilterName = 'active'

        select(model) {
            const attributes = {name: model.get('name')};

            if (model.get('price') !== null && model.get('price') !== undefined) {
                attributes.unitPrice = model.get('price');
            }

            this.model.set(attributes);

            return super.select(model);
        }
    };
});
