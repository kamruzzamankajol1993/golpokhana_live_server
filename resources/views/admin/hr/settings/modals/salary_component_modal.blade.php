<div class="modal fade progga-modal" id="salaryComponentModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-diagram-3-fill me-2"></i><span class="modal-title-text">Add Payroll Component</span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="salaryComponentForm" class="hr-ajax-form" data-entity="salary-components" data-tab="salary-components">
                @csrf
                <input type="hidden" name="id">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-5"><label class="progga-form-label">Component Name *</label><input name="name" class="progga-form-control" placeholder="e.g. Mobile Allowance" required></div>
                        <div class="col-md-4"><label class="progga-form-label">Payslip Label</label><input name="payslip_label" class="progga-form-control" placeholder="Optional display label"></div>
                        <div class="col-md-3"><label class="progga-form-label">Code</label><input name="code" class="progga-form-control" placeholder="MOBILE"></div>

                        <div class="col-md-4"><label class="progga-form-label">Section *</label><select id="hrSalaryComponentGroup" name="component_group" class="progga-select hr-select2"><option value="salary">Salary</option><option value="allowance">Allowance</option><option value="deduction">Deduction</option></select></div>
                        <div class="col-md-4"><label class="progga-form-label">Calculation Type *</label><select id="hrSalaryCalculationType" name="calculation_type" class="progga-select hr-select2"><option value="fixed">Fixed Amount / Rate</option><option value="percentage">Percentage</option><option value="manual">Manual at Payroll</option></select></div>
                        <div class="col-md-4"><label class="progga-form-label">Calculation Rule</label><select id="hrSalaryRuleCode" name="rule_code" class="progga-select hr-select2"><option value="standard">Standard</option><option value="ot_day_off">OT — Day Off</option><option value="ot_gov_off">OT — Govt Holiday</option><option value="late">Late Deduction</option><option value="lwp_absent">LWP + Absent Deduction</option><option value="salary_advance">Salary Advance Auto Recovery</option><option value="loan_adjustment">Loan Auto Recovery</option></select></div>

                        <div class="col-md-4" id="salaryFixedFields">
                            <label class="progga-form-label" id="salaryFixedLabel">Global Amount</label>
                            <div class="input-group"><span class="input-group-text">৳</span><input type="number" step="0.01" min="0" name="default_amount" class="progga-form-control" value="0"><span class="input-group-text d-none" id="salaryFixedSuffix">/ hour</span></div>
                        </div>
                        <div class="col-md-8" id="salaryPercentageFields" style="display:none">
                            <div class="row g-3">
                                <div class="col-md-6"><label class="progga-form-label" id="salaryPercentLabel">Global Percentage</label><div class="input-group"><input type="number" step="0.01" min="0" name="default_percentage" class="progga-form-control" value="0.00"><span class="input-group-text">%</span></div></div>
                                <div class="col-md-6" id="salaryPercentageOfWrap"><label class="progga-form-label">Percentage Of</label><select id="hrSalaryPercentageOf" name="percentage_of" class="progga-select hr-select2"><option value="basic_salary">Basic Salary</option><option value="gross_salary">Gross Salary</option></select></div>
                            </div>
                        </div>
                        <div class="col-12 d-none" id="salaryManualInfo">
                            <div class="alert alert-info mb-0 py-2 px-3"><strong>Payroll-time value:</strong> this component is not stored as an employee/global amount. HR enters its value for the employee when that month's payroll is created. Example: Arrear, Fine, Other, Adjustment (Last Month).</div>
                        </div>
                        <div class="col-12 d-none" id="salaryAutoRecoveryInfo">
                            <div class="alert alert-info mb-0 py-2 px-3"><strong>Automatic recovery:</strong> the amount comes from the Salary Advance / Loan module for the payroll month and cannot be configured as an employee rate.</div>
                        </div>

                        <div class="col-12"><label class="progga-form-label">Description</label><textarea name="description" class="progga-form-control" rows="2"></textarea></div>

                        <div class="col-md-3" id="salaryGlobalConfiguredWrap"><label class="progga-form-label">Global Configured</label><label class="progga-toggle mt-2"><input type="checkbox" name="global_configured" value="1" checked data-on="Configured" data-off="Missing"><span class="progga-toggle-track"><span class="progga-toggle-thumb"></span></span><span class="progga-toggle-label">Configured</span></label></div>
                        <div class="col-md-3" id="salaryApplyAllWrap"><label class="progga-form-label">Apply to All</label><label class="progga-toggle mt-2"><input type="checkbox" name="apply_to_all" value="1" checked data-on="Global" data-off="Assigned Only"><span class="progga-toggle-track"><span class="progga-toggle-thumb"></span></span><span class="progga-toggle-label">Global</span></label></div>
                        <div class="col-md-3" id="salaryEmployeeOverrideWrap"><label class="progga-form-label">Employee Override</label><label class="progga-toggle mt-2"><input type="checkbox" name="allow_employee_override" value="1" checked data-on="Allowed" data-off="Locked"><span class="progga-toggle-track"><span class="progga-toggle-thumb"></span></span><span class="progga-toggle-label">Allowed</span></label></div>
                        <div class="col-md-3"><label class="progga-form-label">Show Zero on Payslip</label><label class="progga-toggle mt-2"><input type="checkbox" name="show_zero_on_payslip" value="1" checked data-on="Show" data-off="Hide"><span class="progga-toggle-track"><span class="progga-toggle-thumb"></span></span><span class="progga-toggle-label">Show</span></label></div>

                        <div class="col-md-3"><label class="progga-form-label">Required</label><label class="progga-toggle mt-2"><input type="checkbox" name="is_required" value="1" data-on="Required" data-off="Optional"><span class="progga-toggle-track"><span class="progga-toggle-thumb"></span></span><span class="progga-toggle-label">Optional</span></label></div>
                        <div class="col-md-3"><label class="progga-form-label">Taxable</label><label class="progga-toggle mt-2"><input type="checkbox" name="is_taxable" value="1" data-on="Taxable" data-off="Not Taxable"><span class="progga-toggle-track"><span class="progga-toggle-thumb"></span></span><span class="progga-toggle-label">Not Taxable</span></label></div>
                        <div class="col-md-3"><label class="progga-form-label">Sort Order</label><input type="number" name="sort_order" class="progga-form-control" min="0" value="0"></div>
                        <div class="col-md-3"><label class="progga-form-label">Status</label><label class="progga-toggle mt-2"><input type="checkbox" name="status" value="1" checked data-on="Active" data-off="Inactive"><span class="progga-toggle-track"><span class="progga-toggle-thumb"></span></span><span class="progga-toggle-label">Active</span></label></div>
                    </div>
                </div>
                <div class="modal-footer"><button type="button" class="progga-btn progga-btn-outline" data-bs-dismiss="modal">Cancel</button><button type="submit" class="progga-btn progga-btn-primary"><i class="bi bi-check-lg"></i> <span class="hr-submit-text">Save Component</span></button></div>
            </form>
        </div>
    </div>
</div>
