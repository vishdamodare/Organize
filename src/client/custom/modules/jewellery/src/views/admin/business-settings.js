define('jewellery:views/admin/business-settings', ['views/settings/record/edit'], (Dep) => {

    return class extends Dep {

        layoutName = 'jewellerySettings'

        saveAndContinueEditingAction = false
    };
});
