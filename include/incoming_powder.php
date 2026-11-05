<?php /* Based on forms/powder.php, with fitted observation fields for screen and print. */ ?>
<div class="incoming-html-form" data-document-type="POWDER">
<div class="document-wrap">

<section class="page powder">
  <header class="header">
    <h1>TOP GLOVE CHEMICAL SDN. BHD.</h1>
    <h2>INCOMING RAW MATERIAL (POWDER) INSPECTION FORM</h2>
  </header>

  <div class="meta three" style="margin-top:12px;margin-bottom:12px;gap:8px 26px">
    <div>
      <div class="meta-row"><span>Chemical Name</span><span class="colon">:</span><?=incoming_example_field('chemical_name',' class="line-input" name="chemical_name"')?></div>
      <div class="meta-row"><span>Supplier/Brand</span><span class="colon">:</span><?=incoming_example_field('supplier_brand',' class="line-input" name="supplier_brand"')?></div>
    </div>
    <div>
      <div class="meta-row"><span>Batch No.</span><span class="colon">:</span><?=incoming_example_field('batch_no',' class="line-input" name="batch_no"')?></div>
      <div class="meta-row"><span>Date Received</span><span class="colon">:</span><?=incoming_example_field('date_received',' class="line-input" name="date_received"')?></div>
    </div>
    <div>
      <div class="meta-row"><span>Inspection Date/Time</span><span class="colon">:</span><?=incoming_example_field('inspection_datetime',' class="line-input" name="inspection_datetime"')?></div>
      <div class="meta-row"><span>Chemical Expiry Date</span><span class="colon">:</span><?=incoming_example_field('expiry_date',' class="line-input" name="expiry_date"')?></div>
    </div>
  </div>

  <table class="form-table table-tight incoming-observation-table">
    <colgroup><col style="width:25%"><col style="width:25%"><col style="width:28%"><col style="width:22%"></colgroup>
    <tr class="row-h-28"><td class="label">Packaging/Sealing Condition</td><td><div class="incoming-packaging"><span>:</span><?=incoming_example_field('packaging_condition',' class="cell-input incoming-condition" name="packaging_condition"')?></div></td><td class="label">Viscosity (cp) @ r.t.</td><td><?=incoming_example_field('viscosity',' class="cell-input" name="viscosity"')?></td></tr>
    <tr class="row-h-28"><td class="label">Color &amp; Appearance</td><td><div class="incoming-observation-value"><span>:</span><?=incoming_example_field('appearance',' class="cell-input" name="appearance"')?></div></td><td class="label">LV/RV, Speed &amp; Time</td><td><?=incoming_example_field('lv_rv_speed_time',' class="cell-input" name="lv_rv_speed_time"')?></td></tr>
    <tr class="row-h-28"><td class="label">pH @r.t.</td><td><div class="incoming-observation-value"><span>:</span><?=incoming_example_field('ph',' class="cell-input" name="ph"')?></div></td><td class="label">Specific gravity g/ml@ Density g/cm<sup>3</sup></td><td><?=incoming_example_field('specific_gravity',' class="cell-input" name="specific_gravity"')?></td></tr>
  </table>

  <div class="split-title"><div>Total Solid Content (TSC):</div><div>Moisture Content:</div></div>
  <table class="form-table table-tight">
    <colgroup>
      <col style="width:27%"><col style="width:8.5%"><col style="width:8.5%"><col style="width:8.5%">
      <col style="width:19%"><col style="width:9.5%"><col style="width:9.5%"><col style="width:9.5%">
    </colgroup>
    <tr class="row-h-26"><th>Sample No.</th><td><?=incoming_example_field('tsc_sample_1',' class="cell-input" name="tsc_sample_1"')?></td><td><?=incoming_example_field('tsc_sample_2',' class="cell-input" name="tsc_sample_2"')?></td><td><?=incoming_example_field('tsc_sample_3',' class="cell-input" name="tsc_sample_3"')?></td><th>Sample No.</th><td><?=incoming_example_field('moist_sample_1',' class="cell-input" name="moist_sample_1"')?></td><td><?=incoming_example_field('moist_sample_2',' class="cell-input" name="moist_sample_2"')?></td><td><?=incoming_example_field('moist_sample_3',' class="cell-input" name="moist_sample_3"')?></td></tr>
    <tr class="row-h-28"><td class="label">Weight of dish, g (A)</td><td><?=incoming_example_field('tsc_a_1',' class="cell-input" name="tsc_a_1"')?></td><td><?=incoming_example_field('tsc_a_2',' class="cell-input" name="tsc_a_2"')?></td><td><?=incoming_example_field('tsc_a_3',' class="cell-input" name="tsc_a_3"')?></td><td class="label">Weight of petri dish, g (A)</td><td><?=incoming_example_field('moist_a_1',' class="cell-input" name="moist_a_1"')?></td><td><?=incoming_example_field('moist_a_2',' class="cell-input" name="moist_a_2"')?></td><td><?=incoming_example_field('moist_a_3',' class="cell-input" name="moist_a_3"')?></td></tr>
    <tr class="row-h-28"><td class="label">Weight of wet sample, g (B)</td><td><?=incoming_example_field('tsc_b_1',' class="cell-input" name="tsc_b_1"')?></td><td><?=incoming_example_field('tsc_b_2',' class="cell-input" name="tsc_b_2"')?></td><td><?=incoming_example_field('tsc_b_3',' class="cell-input" name="tsc_b_3"')?></td><td class="label">Weight of sample, g (B)</td><td><?=incoming_example_field('moist_b_1',' class="cell-input" name="moist_b_1"')?></td><td><?=incoming_example_field('moist_b_2',' class="cell-input" name="moist_b_2"')?></td><td><?=incoming_example_field('moist_b_3',' class="cell-input" name="moist_b_3"')?></td></tr>
    <tr class="row-h-40"><td class="label">Weight of dry sample, g (C)</td><td><?=incoming_example_field('tsc_c_1',' class="cell-input" name="tsc_c_1"')?></td><td><?=incoming_example_field('tsc_c_2',' class="cell-input" name="tsc_c_2"')?></td><td><?=incoming_example_field('tsc_c_3',' class="cell-input" name="tsc_c_3"')?></td><td class="label">Weight of sample in petri dish after<br>heating, g (C)</td><td><?=incoming_example_field('moist_c_1',' class="cell-input" name="moist_c_1"')?></td><td><?=incoming_example_field('moist_c_2',' class="cell-input" name="moist_c_2"')?></td><td><?=incoming_example_field('moist_c_3',' class="cell-input" name="moist_c_3"')?></td></tr>
    <tr class="row-h-50"><td class="label">TSC (%) = (C-A)/B x 100%</td><td><?=incoming_example_field('tsc_result_1',' readonly class="cell-input" name="tsc_result_1"')?></td><td><?=incoming_example_field('tsc_result_2',' readonly class="cell-input" name="tsc_result_2"')?></td><td><?=incoming_example_field('tsc_result_3',' readonly class="cell-input" name="tsc_result_3"')?></td><td class="label">Moisture Content (%)<br><br>(A+B) – C / (A+B) x 100%</td><td><?=incoming_example_field('moist_result_1',' readonly class="cell-input" name="moist_result_1"')?></td><td><?=incoming_example_field('moist_result_2',' readonly class="cell-input" name="moist_result_2"')?></td><td><?=incoming_example_field('moist_result_3',' readonly class="cell-input" name="moist_result_3"')?></td></tr>
    <tr class="row-h-26"><td class="label gray">Average (%)</td><td colspan="3" class="gray"><?=incoming_example_field('tsc_average',' readonly class="cell-input" name="tsc_average"')?></td><td class="label gray">Average (%)</td><td colspan="3" class="gray"><?=incoming_example_field('moist_average',' readonly class="cell-input" name="moist_average"')?></td></tr>
  </table>

  <div class="split-title"><div>Wet Sieve Residue:</div><div style="display:flex;justify-content:space-between;padding-right:95px"><span>Dry Sieve Residue:</span><span>Filter Size: <?=incoming_example_field('filter_size',' class="line-input" name="filter_size" style="width:105px;display:inline-block"')?>&micro;m</span></div></div>
  <table class="form-table table-tight">
    <colgroup>
      <col style="width:25.5%"><col style="width:8.5%"><col style="width:8.5%"><col style="width:8.5%">
      <col style="width:20%"><col style="width:9.7%"><col style="width:9.65%"><col style="width:9.65%">
    </colgroup>
    <tr class="row-h-26"><th>Sample No.</th><td><?=incoming_example_field('wet_sample_1',' class="cell-input" name="wet_sample_1"')?></td><td><?=incoming_example_field('wet_sample_2',' class="cell-input" name="wet_sample_2"')?></td><td><?=incoming_example_field('wet_sample_3',' class="cell-input" name="wet_sample_3"')?></td><th>Sample No.</th><td><?=incoming_example_field('dry_sample_1',' class="cell-input" name="dry_sample_1"')?></td><td><?=incoming_example_field('dry_sample_2',' class="cell-input" name="dry_sample_2"')?></td><td><?=incoming_example_field('dry_sample_3',' class="cell-input" name="dry_sample_3"')?></td></tr>
    <tr class="row-h-28"><td class="label">Weight of sample, g (W1)</td><td><?=incoming_example_field('wet_w1_1',' class="cell-input" name="wet_w1_1"')?></td><td><?=incoming_example_field('wet_w1_2',' class="cell-input" name="wet_w1_2"')?></td><td><?=incoming_example_field('wet_w1_3',' class="cell-input" name="wet_w1_3"')?></td><td class="label">Weight of empty plate, g (A)</td><td><?=incoming_example_field('dry_a_1',' class="cell-input" name="dry_a_1"')?></td><td><?=incoming_example_field('dry_a_2',' class="cell-input" name="dry_a_2"')?></td><td><?=incoming_example_field('dry_a_3',' class="cell-input" name="dry_a_3"')?></td></tr>
    <tr class="row-h-28"><td class="label">Weight of wire mesh, g (W2)</td><td><?=incoming_example_field('wet_w2_1',' class="cell-input" name="wet_w2_1"')?></td><td><?=incoming_example_field('wet_w2_2',' class="cell-input" name="wet_w2_2"')?></td><td><?=incoming_example_field('wet_w2_3',' class="cell-input" name="wet_w2_3"')?></td><td class="label">Weight of sample g (B)</td><td><?=incoming_example_field('dry_b_1',' class="cell-input" name="dry_b_1"')?></td><td><?=incoming_example_field('dry_b_2',' class="cell-input" name="dry_b_2"')?></td><td><?=incoming_example_field('dry_b_3',' class="cell-input" name="dry_b_3"')?></td></tr>
    <tr class="row-h-40"><td class="label">Weight of dried mesh residue, g (W3)</td><td><?=incoming_example_field('wet_w3_1',' class="cell-input" name="wet_w3_1"')?></td><td><?=incoming_example_field('wet_w3_2',' class="cell-input" name="wet_w3_2"')?></td><td><?=incoming_example_field('wet_w3_3',' class="cell-input" name="wet_w3_3"')?></td><td class="label" rowspan="2">Weight of sample after sieve + plate,<br>g (C)</td><td rowspan="2"><?=incoming_example_field('dry_c_1',' class="cell-input" name="dry_c_1"')?></td><td rowspan="2"><?=incoming_example_field('dry_c_2',' class="cell-input" name="dry_c_2"')?></td><td rowspan="2"><?=incoming_example_field('dry_c_3',' class="cell-input" name="dry_c_3"')?></td></tr>
    <tr class="row-h-48"><td class="label">Sieve Residue (%)<br><br>x 100%</td><td><?=incoming_example_field('wet_result_1',' readonly class="cell-input" name="wet_result_1"')?></td><td><?=incoming_example_field('wet_result_2',' readonly class="cell-input" name="wet_result_2"')?></td><td><?=incoming_example_field('wet_result_3',' readonly class="cell-input" name="wet_result_3"')?></td></tr>
    <tr class="row-h-48"><td class="label">Particle &lt; 300 Mesh (%)<br>100% - sieve residue (%)</td><td><?=incoming_example_field('wet_particle_1',' readonly class="cell-input" name="wet_particle_1"')?></td><td><?=incoming_example_field('wet_particle_2',' readonly class="cell-input" name="wet_particle_2"')?></td><td><?=incoming_example_field('wet_particle_3',' readonly class="cell-input" name="wet_particle_3"')?></td><td class="label">Mesh particle (%)<br><br>(C-A)/B x100%</td><td><?=incoming_example_field('dry_result_1',' readonly class="cell-input" name="dry_result_1"')?></td><td><?=incoming_example_field('dry_result_2',' readonly class="cell-input" name="dry_result_2"')?></td><td><?=incoming_example_field('dry_result_3',' readonly class="cell-input" name="dry_result_3"')?></td></tr>
    <tr class="row-h-26"><td class="label gray">Average (%)</td><td colspan="3" class="gray"><?=incoming_example_field('wet_average',' readonly class="cell-input" name="wet_average"')?></td><td class="label gray">Average (%)</td><td colspan="3" class="gray"><?=incoming_example_field('dry_average',' readonly class="cell-input" name="dry_average"')?></td></tr>
  </table>

  <div class="revision">Rev: 0 (LA F46)</div>
</section>

<section class="page powder">
  <header class="header" style="margin-bottom:18px">
    <h1>TOP GLOVE CHEMICAL SDN. BHD.</h1>
    <h2>INCOMING RAW MATERIAL (POWDER) INSPECTION FORM</h2>
  </header>

  <div class="section-title" style="margin-top:14px">Purity:</div>
  <table class="form-table table-tight">
    <colgroup><col style="width:44%"><col style="width:18.67%"><col style="width:18.67%"><col style="width:18.66%"></colgroup>
    <tr class="row-h-28"><th>Sample No.</th><td><?=incoming_example_field('purity_sample_1',' class="cell-input" name="purity_sample_1"')?></td><td><?=incoming_example_field('purity_sample_2',' class="cell-input" name="purity_sample_2"')?></td><td><?=incoming_example_field('purity_sample_3',' class="cell-input" name="purity_sample_3"')?></td></tr>
    <tr class="row-h-28"><td class="label">Weight of sample, g</td><td><?=incoming_example_field('purity_weight_1',' class="cell-input" name="purity_weight_1"')?></td><td><?=incoming_example_field('purity_weight_2',' class="cell-input" name="purity_weight_2"')?></td><td><?=incoming_example_field('purity_weight_3',' class="cell-input" name="purity_weight_3"')?></td></tr>
    <tr class="row-h-28"><td class="label">Normality/ Molarity of titrant (N/M)</td><td><?=incoming_example_field('purity_nm_1',' class="cell-input" name="purity_nm_1"')?></td><td><?=incoming_example_field('purity_nm_2',' class="cell-input" name="purity_nm_2"')?></td><td><?=incoming_example_field('purity_nm_3',' class="cell-input" name="purity_nm_3"')?></td></tr>
    <tr class="row-h-28"><td class="label">Volume of titrant, ml</td><td><?=incoming_example_field('purity_volume_1',' class="cell-input" name="purity_volume_1"')?></td><td><?=incoming_example_field('purity_volume_2',' class="cell-input" name="purity_volume_2"')?></td><td><?=incoming_example_field('purity_volume_3',' class="cell-input" name="purity_volume_3"')?></td></tr>
    <tr class="row-h-28"><td class="label">Purity (%)</td><td><?=incoming_example_field('purity_result_1',' class="cell-input" name="purity_result_1"')?></td><td><?=incoming_example_field('purity_result_2',' class="cell-input" name="purity_result_2"')?></td><td><?=incoming_example_field('purity_result_3',' class="cell-input" name="purity_result_3"')?></td></tr>
    <tr class="row-h-28"><td class="label gray">Average (%)</td><td colspan="3" class="gray"><?=incoming_example_field('purity_average',' readonly class="cell-input" name="purity_average"')?></td></tr>
  </table>

  <div class="disposition" style="margin-top:2px">
    <span class="lead">Disposition (/)&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;:</span>
    <label class="check-item">Pass <?=incoming_example_field('disp_pass',' type="checkbox" name="disp_pass"')?></label>
    <label class="check-item">Fail <?=incoming_example_field('disp_fail',' type="checkbox" name="disp_fail"')?></label>
    <label class="check-item">Accept <?=incoming_example_field('disp_accept',' type="checkbox" name="disp_accept"')?></label>
    <label class="check-item">Accept with LOC <?=incoming_example_field('disp_accept_loc',' type="checkbox" name="disp_accept_loc"')?></label>
    <label class="check-item">Reject with LOC <?=incoming_example_field('disp_reject_loc',' type="checkbox" name="disp_reject_loc"')?></label>
  </div>

  <div class="footer-signoff signoff">
    <div>
      <div class="box"><span>Tested&nbsp;&nbsp;&nbsp;&nbsp;by</span><span>:</span><?=incoming_example_field('tested_by_page2',' class="line-input" name="tested_by_page2"')?></div>
      <div class="box"><span>Position by</span><span>:</span><span>Lab Assistant, QA</span></div>
    </div>
    <div>
      <div class="box"><span>Verified by</span><span>:</span><?=incoming_example_field('verified_by_page2',' class="line-input" name="verified_by_page2"')?></div>
      <div class="box"><span>Position by</span><span>:</span><span>Officer, QA</span></div>
      <div class="box"><span>Date</span><span>:</span><?=incoming_example_field('verified_date_page2',' class="line-input" name="verified_date_page2"')?></div>
    </div>
  </div>
  <div class="revision">Rev: 0 (LA F46)</div>
</section>

</div>
</div>
