define('jewellery:views/reports/index', ['view'], (Dep) => {

    /**
     * Reports page. Figures come from GET /JewelleryReport/summary, which applies the
     * user's record permissions on the server; CSV export reuses the same data.
     */
    return class extends Dep {

        templateContent = `
            <div class="page-header"><h3>Reports</h3></div>
            <div class="jw-report-toolbar">
                <div class="btn-group" role="group" aria-label="Period">
                    {{#each periods}}
                        <button type="button" class="btn btn-sm {{#if active}}btn-primary{{else}}btn-default{{/if}}"
                            data-action="setPeriod" data-period="{{value}}">{{label}}</button>
                    {{/each}}
                </div>
                <span class="jw-custom">
                    <label for="jw-r-from">From</label>
                    <input type="date" class="form-control input-sm" id="jw-r-from" data-name="from" value="{{from}}">
                    <label for="jw-r-to">To</label>
                    <input type="date" class="form-control input-sm" id="jw-r-to" data-name="to" value="{{to}}">
                    <button type="button" class="btn btn-sm btn-default" data-action="applyCustom">Apply</button>
                </span>
                <button type="button" class="btn btn-sm btn-default jw-export" data-action="exportCsv" {{#unless ready}}disabled{{/unless}}>
                    <span class="fas fa-download"></span> Export CSV</button>
            </div>

            {{#if loading}}<p class="text-muted"><span class="fas fa-spinner fa-spin"></span> Loading…</p>{{/if}}
            {{#if error}}<div class="alert alert-danger">{{error}}</div>{{/if}}

            {{#if ready}}
            <div class="jw-report-kpis">
                {{#each kpis}}
                    <div class="jw-tile"><span class="jw-tile-label">{{label}}</span><span class="jw-tile-value">{{value}}</span>
                    {{#if sub}}<span class="jw-tile-sub">{{sub}}</span>{{/if}}</div>
                {{/each}}
            </div>
            <div class="jw-report-grid">
                {{#each sections}}
                <section class="panel panel-default jw-report">
                    <div class="panel-heading"><h4 class="panel-title">{{title}}</h4></div>
                    <div class="panel-body">
                        {{#if note}}<p class="text-muted small">{{note}}</p>{{/if}}
                        {{#if rows.length}}
                        <div class="table-responsive">
                        <table class="table table-condensed">
                            <thead><tr>{{#each columns}}<th scope="col" {{#if num}}class="text-right"{{/if}}>{{label}}</th>{{/each}}</tr></thead>
                            <tbody>
                            {{#each rows}}
                                <tr>{{#each cells}}<td {{#if num}}class="text-right"{{/if}}>
                                    {{#if bar}}<span class="jw-inline-bar" style="width: {{bar}}%"></span>{{/if}}{{value}}</td>{{/each}}</tr>
                            {{/each}}
                            </tbody>
                        </table>
                        </div>
                        {{else}}
                            <p class="text-muted">No data for this period.</p>
                        {{/if}}
                    </div>
                </section>
                {{/each}}
            </div>
            <p class="text-muted small">
                Definitions: <b>Order value</b> = grand total of orders dated in the period, excluding Draft and Cancelled
                (not payment received). <b>Won value</b> = expected value of deals marked Won in the period.
                <b>Follow-up completion</b> = tasks due in the period that are Completed. Figures include only records you are allowed to see.
            </p>
            {{/if}}
        `

        events = {
            'click [data-action="setPeriod"]': function (e) {
                this.period = e.currentTarget.dataset.period;
                this.load();
            },
            'click [data-action="applyCustom"]': function () {
                const from = this.$el.find('[data-name="from"]').val();
                const to = this.$el.find('[data-name="to"]').val();

                if (!from || !to) {
                    Espo.Ui.warning('Choose both dates.');

                    return;
                }

                this.period = 'custom';
                this.from = from;
                this.to = to;
                this.load();
            },
            'click [data-action="exportCsv"]': function () {
                this.exportCsv();
            },
        }

        setup() {
            this.period = 'month';
            this.result = null;
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

            Espo.Ajax.getRequest('JewelleryReport/summary', params)
                .then(result => {
                    this.result = result;
                    this.from = result.period.from;
                    this.to = result.period.to;
                })
                .catch(xhr => {
                    xhr.errorIsHandled = true;
                    this.result = null;
                    this.error = xhr.status === 403 ? 'You do not have access to reports.' :
                        (xhr.getResponseHeader && xhr.getResponseHeader('X-Status-Reason')) || 'Could not load reports.';
                })
                .finally(() => {
                    this.loading = false;
                    this.reRender();
                });
        }

        money(value) {
            const currency = this.getConfig().get('defaultCurrency') || 'INR';

            return new Intl.NumberFormat('en-IN', {style: 'currency', currency, maximumFractionDigits: 2})
                .format(parseFloat(value || 0));
        }

        opt(value, field, scope) {
            if (value === null || value === '') {
                return '(none)';
            }

            return this.getLanguage().translateOption(value, field, scope);
        }

        /** Builds plain {title, columns, rows} tables; used for both screen and CSV. */
        buildSections() {
            const r = this.result;
            const sections = [];

            const grouped = (title, list, field, scope, withTotal, note) => {
                if (!list) {
                    return; // no permission for this record type
                }

                const max = Math.max(1, ...list.map(g => withTotal ? parseFloat(g.total) || 0 : g.count));
                const columns = [{label: 'Group'}, {label: 'Count', num: true}];

                if (withTotal) {
                    columns.push({label: 'Value', num: true});
                }

                sections.push({
                    title, note, columns,
                    rows: list.map(g => {
                        const cells = [
                            {value: field ? this.opt(g.key, field, scope) : (g.key || '(none)')},
                            {value: g.count, num: true, bar: withTotal ? null : Math.round(g.count / max * 100)},
                        ];

                        if (withTotal) {
                            cells.push({value: this.money(g.total), num: true, raw: g.total,
                                bar: Math.round((parseFloat(g.total) || 0) / max * 100)});
                        }

                        return {cells};
                    }),
                });
            };

            grouped('Enquiries by source', r.enquiriesBySource, 'source', 'Enquiry', false, 'Enquiries dated in the period.');
            grouped('Enquiries by status', r.enquiriesByStatus, 'status', 'Enquiry', false, 'Current status of enquiries dated in the period.');
            grouped('Enquiries by category', r.enquiriesByCategory, 'category', 'Enquiry', false);
            grouped('Deals by stage', r.dealsByStage, 'stage', 'Opportunity', true, 'Deals created in the period, by current stage. Expected value.');
            grouped('Won vs lost', r.wonVsLost, 'stage', 'Opportunity', true, 'Deals closed in the period.');
            grouped('Lost reasons', r.lostReasons, 'lostReason', 'Opportunity', false);

            if (r.quotations && r.quotations.byStatus) {
                grouped('Quotations by status', r.quotations.byStatus, 'status', 'Quotation', true, 'Quotations issued in the period.');
            }

            grouped('Orders by status', r.ordersByStatus, 'status', 'CustomerOrder', true, 'Orders dated in the period.');
            grouped('Sales value over time', r.salesOverTime, null, null, true,
                'Order value by order date (excludes Draft and Cancelled).');

            if (r.salespeople && r.salespeople.length) {
                sections.push({
                    title: 'Salesperson performance',
                    columns: [{label: 'Salesperson'}, {label: 'Enquiries', num: true}, {label: 'Won', num: true},
                        {label: 'Won value', num: true}, {label: 'Lost', num: true}, {label: 'Order value', num: true},
                        {label: 'Tasks done', num: true}, {label: 'Overdue now', num: true}],
                    rows: r.salespeople.map(p => ({cells: [
                        {value: p.name},
                        {value: p.enquiries || 0, num: true},
                        {value: p.won || 0, num: true},
                        {value: this.money(p.wonValue), num: true, raw: p.wonValue || '0'},
                        {value: p.lost || 0, num: true},
                        {value: this.money(p.ordersValue), num: true, raw: p.ordersValue || '0'},
                        {value: p.tasksCompleted || 0, num: true},
                        {value: p.tasksOverdue || 0, num: true},
                    ]})),
                });
            }

            return sections;
        }

        buildKpis() {
            const r = this.result;
            const f = r.followUps || {};
            const q = r.quotations || {};
            const kpis = [];

            if (f.due !== null && f.due !== undefined) {
                const rate = f.due ? Math.round(f.completed / f.due * 100) + '%' : '—';
                kpis.push({label: 'Follow-up completion', value: rate, sub: `${f.completed} of ${f.due} due in period`});
                kpis.push({label: 'Overdue tasks (now)', value: f.overdueNow});
            }

            if (q.issued !== null && q.issued !== undefined) {
                kpis.push({label: 'Quotations sent', value: q.issued});
                kpis.push({label: 'Quotations accepted', value: q.accepted, sub: this.money(q.acceptedValue)});
            }

            return kpis;
        }

        data() {
            const ready = !!this.result && !this.loading;

            return {
                periods: [['week', 'This week'], ['month', 'This month'], ['year', 'This year']]
                    .map(([value, label]) => ({value, label, active: value === this.period})),
                from: this.from || '',
                to: this.to || '',
                loading: this.loading,
                error: this.error,
                ready,
                kpis: ready ? this.buildKpis() : [],
                sections: ready ? this.buildSections() : [],
            };
        }

        exportCsv() {
            const esc = v => {
                const s = String(v === null || v === undefined ? '' : v);
                // Neutralise spreadsheet formulas (CSV injection) and quote.
                const safe = /^[=+\-@\t\r]/.test(s) ? "'" + s : s;

                return '"' + safe.replace(/"/g, '""') + '"';
            };

            const lines = [`Report period,${this.from},${this.to}`];

            this.buildKpis().forEach(k => lines.push([esc(k.label), esc(k.value), esc(k.sub || '')].join(',')));

            this.buildSections().forEach(s => {
                lines.push('', esc(s.title), s.columns.map(c => esc(c.label)).join(','));
                s.rows.forEach(row => lines.push(row.cells.map(c => esc(c.raw !== undefined ? c.raw : c.value)).join(',')));
            });

            const blob = new Blob([lines.join('\r\n')], {type: 'text/csv;charset=utf-8'});
            const a = document.createElement('a');
            a.href = URL.createObjectURL(blob);
            a.download = `jewellery-crm-report-${this.from}-to-${this.to}.csv`;
            document.body.appendChild(a);
            a.click();
            a.remove();
            setTimeout(() => URL.revokeObjectURL(a.href), 1000);
        }
    };
});
