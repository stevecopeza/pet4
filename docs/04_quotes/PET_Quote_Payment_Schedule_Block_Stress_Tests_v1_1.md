# PET Payment Schedule Stress Tests (v1.1)

- Totals mismatch blocks acceptance
- Percent rounding mismatch blocks acceptance
- Missing trigger payload blocks acceptance
- Attempt to edit accepted schedule -> hard error
- ON_ACCEPTANCE emits due events once
- ON_DATE evaluator idempotent
- ON_DOMAIN_EVENT emits once
- Installment series requires projected_finish_date
- Installment materialization sums exactly
- Missing tables -> no fatal error
