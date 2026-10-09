define('jewellery:controllers/jewellery-report', ['controller'], (Dep) => {

    return class extends Dep {

        defaultAction = 'index'

        checkAccess() {
            // UI convenience only — the API enforces the same permission.
            return this.getAcl().check('JewelleryReport');
        }

        actionIndex() {
            this.main('jewellery:views/reports/index', {});
        }
    };
});
