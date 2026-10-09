define('jewellery:views/dashlets/overview', ['views/dashlets/abstract/base'], (Dep) => {

    /**
     * Business overview: live, permission-scoped figures from /JewelleryReport/overview.
     * Every tile links to the matching filtered list.
     */
    return class extends Dep {

        name = 'JewelleryOverview'

        templateContent = `
            <div class="jw-overview">
                <div class="jw-welcome">
                    <div>
                        <h3>{{greeting}}, {{firstName}}</h3>
                        <p>Here's how {{companyName}} is doing.</p>
                    </div>
                    <div class="jw-quick">
                        {{#each quickActions}}
                            <a class="btn {{#if primary}}btn-primary{{else}}btn-default{{/if}} btn-sm" href="{{href}}"><span class="{{icon}}"></span> {{label}}</a>
                        {{/each}}
                    </div>
                </div>
                <div class="jw-period" role="group" aria-label="Period">
                    {{#each periods}}
                        <button type="button" class="btn btn-sm {{#if active}}btn-primary{{else}}btn-default{{/if}}"
                            data-action="setPeriod" data-period="{{value}}" aria-pressed="{{#if active}}true{{else}}false{{/if}}">{{label}}</button>
                    {{/each}}
                    <span class="jw-custom {{#unless isCustom}}hidden{{/unless}}">
                        <label class="sr-only" for="jw-from-{{id}}">From</label>
                        <input type="date" class="form-control input-sm" id="jw-from-{{id}}" data-name="from" value="{{from}}">
                        <label class="sr-only" for="jw-to-{{id}}">To</label>
                        <input type="date" class="form-control input-sm" id="jw-to-{{id}}" data-name="to" value="{{to}}">
                        <button type="button" class="btn btn-sm btn-default" data-action="applyCustom">Apply</button>
                    </span>
                    {{#if rangeText}}<span class="text-muted jw-range">{{rangeText}}</span>{{/if}}
                </div>

                {{#if loading}}
                    <div class="jw-loading text-muted"><span class="fas fa-spinner fa-spin"></span> Loading…</div>
                {{else}}
                {{#if error}}
                    <div class="alert alert-danger">{{error}}</div>
                {{else}}

                {{#if isEmpty}}
                    <div class="jw-empty">
                        <h4>Welcome! Let's set up your jewellery CRM.</h4>
                        <p class="text-muted">You have no customers yet. Start with one of these steps:</p>
                        <div class="jw-empty-actions">
                            <a class="btn btn-primary" href="#Account/create"><span class="fas fa-plus"></span> Add your first customer</a>
                            {{#if canImport}}<a class="btn btn-default" href="#Import"><span class="fas fa-file-import"></span> Import customers (CSV)</a>{{/if}}
                            <a class="btn btn-default" href="client/custom/modules/jewellery/res/customer-import-template.csv" download>
                                <span class="fas fa-download"></span> Download CSV template</a>
                            {{#if isAdmin}}
                                <a class="btn btn-default" href="#User/create"><span class="fas fa-user-plus"></span> Add an employee</a>
                                <a class="btn btn-default" href="#Admin/jewellerySettings"><span class="fas fa-gem"></span> Business settings</a>
                            {{/if}}
                        </div>
                    </div>
                {{/if}}

                <div class="jw-tiles">
                    {{#each tiles}}
                        <a class="jw-tile {{#if tone}}jw-{{tone}}{{/if}}" href="{{href}}" title="{{hint}}">
                            <span class="jw-tile-label">{{label}}</span>
                            <span class="jw-tile-value">{{value}}</span>
                            {{#if sub}}<span class="jw-tile-sub">{{sub}}</span>{{/if}}
                        </a>
                    {{/each}}
                </div>

                <div class="jw-columns">
                    <section class="jw-panel">
                        <h5>Sales pipeline (open deals)</h5>
                        {{#if stages.length}}
                            {{#each stages}}
                                <a class="jw-bar-row" href="#Opportunity">
                                    <span class="jw-bar-label">{{label}}</span>
                                    <span class="jw-bar"><span style="width: {{pct}}%"></span></span>
                                    <span class="jw-bar-value">{{count}} · {{amount}}</span>
                                </a>
                            {{/each}}
                        {{else}}
                            <p class="text-muted">No open deals. Convert an enquiry to start your pipeline.</p>
                        {{/if}}
                    </section>
                    <section class="jw-panel">
                        <h5>Upcoming follow-ups</h5>
                        {{#if tasks.length}}
                            <ul class="jw-task-list">
                            {{#each tasks}}
                                <li>
                                    <a href="#Task/view/{{id}}" title="{{name}}{{#if parentName}} · {{parentName}}{{/if}}">{{name}}{{#if parentName}}<span class="text-muted"> · {{parentName}}</span>{{/if}}</a>
                                    <span class="jw-due {{#if overdue}}text-danger{{else}}text-muted{{/if}}">{{due}}</span>
                                </li>
                            {{/each}}
                            </ul>
                        {{else}}
                            <p class="text-muted">Nothing scheduled. <a href="#Task/create">Schedule a follow-up</a></p>
                        {{/if}}
                    </section>
                </div>
                <p class="jw-footnote text-muted">
                    Order value counts orders dated in the period that are not Draft or Cancelled — it is not payment received.
                    Won deal value is the expected value of deals marked Won.
                </p>
                {{/if}}
                {{/if}}
            </div>
        `

        events = {
            'click [data-action="setPeriod"]': function (e) {
                this.period = e.currentTarget.dataset.period;

                if (this.period !== 'custom') {
                    this.load();
                } else {
                    this.reRender();
                }
            },
            'click [data-action="applyCustom"]': function () {
                this.from = this.$el.find('[data-name="from"]').val();
                this.to = this.$el.find('[data-name="to"]').val();

                if (!this.from || !this.to) {
                    Espo.Ui.warning('Choose both dates.');

                    return;
                }

                this.load();
            },
        }

        setup() {
            this.period = this.period || 'month';
            this.from = null;
            this.to = null;
            this.result = null;
            this.loading = true;
            this.load();
        }

        actionRefresh() {
            this.load();
        }

        load() {
            this.loading = true;
            this.error = null;

            if (this.isRendered()) {
                this.reRender();
            }

            const params = {period: this.period};

            if (this.period === 'custom') {
                params.from = this.from;
                params.to = this.to;
            }

            Espo.Ajax.getRequest('JewelleryReport/overview', params)
                .then(result => {
                    this.result = result;
                })
                .catch(xhr => {
                    xhr.errorIsHandled = true;
                    this.error = 'Could not load figures. Please refresh.';
                })
                .finally(() => {
                    this.loading = false;
                    this.reRender();
                });
        }

        money(value) {
            if (value === null || value === undefined) {
                return '—';
            }

            const currency = this.getConfig().get('defaultCurrency') || 'INR';

            return new Intl.NumberFormat('en-IN', {style: 'currency', currency, maximumFractionDigits: 0})
                .format(parseFloat(value));
        }

        num(value) {
            return value === null || value === undefined ? '—' : String(value);
        }

        data() {
            const r = this.result || {};
            const periods = [['today', 'Today'], ['week', 'This week'], ['month', 'This month'], ['year', 'This year'], ['custom', 'Custom']]
                .map(([value, label]) => ({value, label, active: value === this.period}));

            const tiles = [
                {label: 'Customers', value: this.num(r.totalCustomers), href: '#Account', hint: 'All customers'},
                {label: 'New enquiries', value: this.num(r.newEnquiries), href: '#Enquiry', hint: 'Enquiries dated in the selected period'},
                {label: 'Open deals', value: this.num(r.openDeals), sub: this.money(r.openPipelineValue) + ' pipeline',
                    href: '#Opportunity/list/primaryFilter=open', hint: 'Deals not yet Won or Lost'},
                {label: 'Due today', value: this.num(r.followUpsDueToday), href: '#Task/list/primaryFilter=todays',
                    tone: r.followUpsDueToday ? 'warn' : null, hint: 'Open tasks due today'},
                {label: 'Overdue', value: this.num(r.overdueFollowUps), href: '#Task/list/primaryFilter=overdue',
                    tone: r.overdueFollowUps ? 'danger' : null, hint: 'Open tasks past their due time'},
                {label: 'Won deals', value: this.num(r.wonDeals), sub: this.money(r.wonDealValue),
                    href: '#Opportunity/list/primaryFilter=won', hint: 'Deals marked Won in the selected period'},
                {label: 'Order value', value: this.money(r.orderValue), href: '#CustomerOrder/list/primaryFilter=active',
                    tone: 'gold', hint: 'Orders dated in the period, excluding Draft and Cancelled'},
            ].filter(t => t.value !== '—');

            const stageRows = r.pipelineByStage || [];
            const max = Math.max(1, ...stageRows.map(s => parseFloat(s.total) || 0));
            const stages = stageRows.map(s => ({
                label: this.getLanguage().translateOption(s.key, 'stage', 'Opportunity'),
                count: s.count,
                amount: this.money(s.total),
                pct: Math.max(2, Math.round((parseFloat(s.total) || 0) / max * 100)),
            }));

            const tasks = (r.upcomingTasks || []).map(t => ({
                ...t,
                due: t.dateEnd ? this.getDateTime().toDisplay(t.dateEnd) : '',
            }));

            const hour = new Date().getHours();
            const acl = this.getAcl();
            const quickActions = [
                {label: 'New Customer', href: '#Account/create', icon: 'fas fa-user-plus', scope: 'Account', primary: true},
                {label: 'New Enquiry', href: '#Enquiry/create', icon: 'fas fa-gem', scope: 'Enquiry'},
                {label: 'New Quotation', href: '#Quotation/create', icon: 'fas fa-file-invoice', scope: 'Quotation'},
                {label: 'Schedule Follow-up', href: '#Task/create', icon: 'fas fa-calendar-check', scope: 'Task'},
            ].filter(a => acl.check(a.scope, 'create'));

            return {
                greeting: hour < 12 ? 'Good morning' : (hour < 17 ? 'Good afternoon' : 'Good evening'),
                firstName: this.getUser().get('firstName') || this.getUser().get('name'),
                companyName: this.getConfig().get('jewelleryCompanyName') || 'your business',
                quickActions,
                id: this.id,
                periods,
                isCustom: this.period === 'custom',
                from: this.from || (r.period && r.period.from) || '',
                to: this.to || (r.period && r.period.to) || '',
                rangeText: r.period ? `${this.getDateTime().toDisplayDate(r.period.from)} – ${this.getDateTime().toDisplayDate(r.period.to)}` : '',
                loading: this.loading,
                error: this.error,
                isEmpty: r.totalCustomers === 0,
                isAdmin: this.getUser().isAdmin(),
                canImport: this.getAcl().check('Import'),
                tiles,
                stages,
                tasks,
            };
        }
    };
});
