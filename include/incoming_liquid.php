<?php /* Based on forms/liquid.php, with fitted observation fields for screen and print. */ ?>
<div class="incoming-html-form" data-document-type="LIQUID">
<div class="document-wrap">

<section class="page liquid">
  <header class="header">
    <h1>TOP GLOVE CHEMICAL SDN. BHD.</h1>
    <h2>INCOMING RAW MATERIAL (LIQUID) INSPECTION FORM</h2>
  </header>

  <div class="meta two" style="margin-top:14px">
    <div>
      <div class="meta-row"><span>Chemical Name</span><span class="colon">:</span><?=incoming_example_field('chemical_name',' class="line-input" name="chemical_name"')?></div>
      <div class="meta-row"><span>Supplier/Brand</span><span class="colon">:</span><div class="incoming-supplier-picker"><?=incoming_example_field('supplier_brand',' class="line-input" name="supplier_brand" aria-label="Supplier / Brand"')?><span class="incoming-supplier-value screen-only" aria-hidden="true">Select supplier</span></div></div>
      <div class="meta-row"><span>Batch No.</span><span class="colon">:</span><?=incoming_example_field('batch_no',' class="line-input" name="batch_no"')?></div>
    </div>
    <div>
      <div class="meta-row"><span>Date Received</span><span class="colon">:</span><?=incoming_example_field('date_received',' class="line-input" name="date_received"')?></div>
      <div class="meta-row"><span>Inspection Date/Time</span><span class="colon">:</span><?=incoming_example_field('inspection_datetime',' class="line-input" name="inspection_datetime"')?></div>
      <div class="meta-row"><span>Chemical Expiry Date</span><span class="colon">:</span><?=incoming_example_field('expiry_date',' class="line-input" name="expiry_date"')?></div>
    </div>
  </div>

  <table class="form-table table-tight incoming-observation-table">
    <colgroup><col style="width:49%"><col style="width:15.3%"><col style="width:35.7%"></colgroup>
    <tr class="row-h-28"><td class="label">Packaging/Sealing Condition</td><td colspan="2"><div class="incoming-packaging"><span>:</span><?=incoming_example_field('packaging_condition',' class="cell-input incoming-condition" name="packaging_condition"')?><span class="incoming-remark-label">Remark:</span><?=incoming_example_field('packaging_remark',' class="line-input incoming-remark" name="packaging_remark"')?></div></td></tr>
    <tr class="row-h-28"><td class="label">Color &amp; Appearance</td><td colspan="2"><div class="incoming-observation-value"><span>:</span><?=incoming_example_field('appearance',' class="cell-input" name="appearance"')?></div></td></tr>
    <tr class="row-h-28"><td class="label">pH @r.t</td><td colspan="2"><div class="incoming-observation-value"><span>:</span><?=incoming_example_field('ph',' class="cell-input" name="ph"')?></div></td></tr>
    <tr class="row-h-28"><td class="label">Viscosity (cp) @ r.t.</td><td colspan="2"><div class="incoming-observation-value"><span>:</span><?=incoming_example_field('viscosity',' class="cell-input" name="viscosity"')?></div></td></tr>
    <tr class="row-h-28"><td class="label">LV/RV, Speed &amp; Time</td><td colspan="2"><div class="incoming-observation-value"><span>:</span><?=incoming_example_field('lv_rv_speed_time',' class="cell-input" name="lv_rv_speed_time"')?></div></td></tr>
    <tr class="row-h-28"><td class="label">Specific gravity g/ml @ Density g/cm<sup>3</sup></td><td colspan="2"><div class="incoming-observation-value"><span>:</span><?=incoming_example_field('specific_gravity',' class="cell-input" name="specific_gravity"')?></div></td></tr>
    <tr class="row-h-28 incoming-particle-row"><td class="label" rowspan="4">Particle Size (&micro;m)</td><td class="label">D10</td><td><div class="incoming-observation-value"><span>:</span><?=incoming_example_field('particle_d10',' class="cell-input" name="particle_d10"')?></div></td></tr>
    <tr class="row-h-28 incoming-particle-row"><td class="label">D50/ Median</td><td><div class="incoming-observation-value"><span>:</span><?=incoming_example_field('particle_d50',' class="cell-input" name="particle_d50"')?></div></td></tr>
    <tr class="row-h-28 incoming-particle-row"><td class="label">D90</td><td><div class="incoming-observation-value"><span>:</span><?=incoming_example_field('particle_d90',' class="cell-input" name="particle_d90"')?></div></td></tr>
    <tr class="row-h-28 incoming-particle-row"><td class="label">Mean</td><td><div class="incoming-observation-value"><span>:</span><?=incoming_example_field('particle_mean',' class="cell-input" name="particle_mean"')?></div></td></tr>
  </table>

  <div class="section-title inline"><span>Total Solid Content (TSC)</span><span style="margin-left:35px">:</span></div>
  <table class="form-table table-tight">
    <colgroup><col style="width:46%"><col style="width:18%"><col style="width:18%"><col style="width:18%"></colgroup>
    <tr class="row-h-28"><th>Sample No.</th><td><?=incoming_example_field('tsc_sample_1',' class="cell-input" name="tsc_sample_1"')?></td><td><?=incoming_example_field('tsc_sample_2',' class="cell-input" name="tsc_sample_2"')?></td><td><?=incoming_example_field('tsc_sample_3',' class="cell-input" name="tsc_sample_3"')?></td></tr>
    <tr class="row-h-28"><td class="label">Weight of dish, g (A)</td><td><?=incoming_example_field('tsc_a_1',' class="cell-input" name="tsc_a_1"')?></td><td><?=incoming_example_field('tsc_a_2',' class="cell-input" name="tsc_a_2"')?></td><td><?=incoming_example_field('tsc_a_3',' class="cell-input" name="tsc_a_3"')?></td></tr>
    <tr class="row-h-28"><td class="label">Weight of wet sample, g (B)</td><td><?=incoming_example_field('tsc_b_1',' class="cell-input" name="tsc_b_1"')?></td><td><?=incoming_example_field('tsc_b_2',' class="cell-input" name="tsc_b_2"')?></td><td><?=incoming_example_field('tsc_b_3',' class="cell-input" name="tsc_b_3"')?></td></tr>
    <tr class="row-h-28"><td class="label">Weight of dry sample, g (C)</td><td><?=incoming_example_field('tsc_c_1',' class="cell-input" name="tsc_c_1"')?></td><td><?=incoming_example_field('tsc_c_2',' class="cell-input" name="tsc_c_2"')?></td><td><?=incoming_example_field('tsc_c_3',' class="cell-input" name="tsc_c_3"')?></td></tr>
    <tr class="row-h-34"><td class="label">TSC (%) = (C-A)/B x100%</td><td><?=incoming_example_field('tsc_result_1',' readonly class="cell-input" name="tsc_result_1"')?></td><td><?=incoming_example_field('tsc_result_2',' readonly class="cell-input" name="tsc_result_2"')?></td><td><?=incoming_example_field('tsc_result_3',' readonly class="cell-input" name="tsc_result_3"')?></td></tr>
    <tr class="row-h-28"><td class="label gray">Average (%)</td><td colspan="3" class="gray"><?=incoming_example_field('tsc_average',' readonly class="cell-input" name="tsc_average"')?></td></tr>
  </table>

  <div class="section-title inline"><span>Wet Sieve Residue</span><span style="margin-left:49px">:</span></div>
  <table class="form-table table-tight">
    <colgroup><col style="width:44%"><col style="width:18.67%"><col style="width:18.67%"><col style="width:18.66%"></colgroup>
    <tr class="row-h-28"><th>Sample No.</th><td><?=incoming_example_field('wet_sample_1',' class="cell-input" name="wet_sample_1"')?></td><td><?=incoming_example_field('wet_sample_2',' class="cell-input" name="wet_sample_2"')?></td><td><?=incoming_example_field('wet_sample_3',' class="cell-input" name="wet_sample_3"')?></td></tr>
    <tr class="row-h-28"><td class="label">Weight of sample, g (W1)</td><td><?=incoming_example_field('wet_w1_1',' class="cell-input" name="wet_w1_1"')?></td><td><?=incoming_example_field('wet_w1_2',' class="cell-input" name="wet_w1_2"')?></td><td><?=incoming_example_field('wet_w1_3',' class="cell-input" name="wet_w1_3"')?></td></tr>
    <tr class="row-h-28"><td class="label">Weight of wire mesh, g (W2)</td><td><?=incoming_example_field('wet_w2_1',' class="cell-input" name="wet_w2_1"')?></td><td><?=incoming_example_field('wet_w2_2',' class="cell-input" name="wet_w2_2"')?></td><td><?=incoming_example_field('wet_w2_3',' class="cell-input" name="wet_w2_3"')?></td></tr>
    <tr class="row-h-28"><td class="label">Weight of dried mesh residue, g (W3)</td><td><?=incoming_example_field('wet_w3_1',' class="cell-input" name="wet_w3_1"')?></td><td><?=incoming_example_field('wet_w3_2',' class="cell-input" name="wet_w3_2"')?></td><td><?=incoming_example_field('wet_w3_3',' class="cell-input" name="wet_w3_3"')?></td></tr>
    <tr class="row-h-42"><td class="label">Sieve Residue (%)<br><br>x 100%</td><td><?=incoming_example_field('wet_result_1',' readonly class="cell-input" name="wet_result_1"')?></td><td><?=incoming_example_field('wet_result_2',' readonly class="cell-input" name="wet_result_2"')?></td><td><?=incoming_example_field('wet_result_3',' readonly class="cell-input" name="wet_result_3"')?></td></tr>
    <tr class="row-h-42"><td class="label">Particle &lt; 300 Mesh (%)<br><br>100% - Sieve Residue (%)</td><td><?=incoming_example_field('wet_particle_1',' readonly class="cell-input" name="wet_particle_1"')?></td><td><?=incoming_example_field('wet_particle_2',' readonly class="cell-input" name="wet_particle_2"')?></td><td><?=incoming_example_field('wet_particle_3',' readonly class="cell-input" name="wet_particle_3"')?></td></tr>
    <tr class="row-h-28"><td class="label gray">Average (%)</td><td colspan="3" class="gray"><?=incoming_example_field('wet_average',' readonly class="cell-input" name="wet_average"')?></td></tr>
  </table>

  <div class="revision">Rev: 0 (LA F46)</div>
</section>

<section class="page liquid">
  <header class="header">
    <h1>TOP GLOVE CHEMICAL SDN. BHD.</h1>
    <h2>INCOMING RAW MATERIAL (LIQUID) INSPECTION FORM</h2>
  </header>

  <div class="section-title inline" style="margin-top:28px"><span>Purity</span><span style="margin-left:84px">:</span></div>
  <table class="form-table table-tight">
    <colgroup><col style="width:44%"><col style="width:18.67%"><col style="width:18.67%"><col style="width:18.66%"></colgroup>
    <tr class="row-h-28"><th>Sample No.</th><td><?=incoming_example_field('purity_sample_1',' class="cell-input" name="purity_sample_1"')?></td><td><?=incoming_example_field('purity_sample_2',' class="cell-input" name="purity_sample_2"')?></td><td><?=incoming_example_field('purity_sample_3',' class="cell-input" name="purity_sample_3"')?></td></tr>
    <tr class="row-h-28"><td class="label">Weight of sample, g</td><td><?=incoming_example_field('purity_weight_1',' class="cell-input" name="purity_weight_1"')?></td><td><?=incoming_example_field('purity_weight_2',' class="cell-input" name="purity_weight_2"')?></td><td><?=incoming_example_field('purity_weight_3',' class="cell-input" name="purity_weight_3"')?></td></tr>
    <tr class="row-h-34"><td class="label">Normality/ Molarity of titrant (N/M)</td><td><?=incoming_example_field('purity_nm_1',' class="cell-input" name="purity_nm_1"')?></td><td><?=incoming_example_field('purity_nm_2',' class="cell-input" name="purity_nm_2"')?></td><td><?=incoming_example_field('purity_nm_3',' class="cell-input" name="purity_nm_3"')?></td></tr>
    <tr class="row-h-28"><td class="label">Volume of titrant, ml</td><td><?=incoming_example_field('purity_volume_1',' class="cell-input" name="purity_volume_1"')?></td><td><?=incoming_example_field('purity_volume_2',' class="cell-input" name="purity_volume_2"')?></td><td><?=incoming_example_field('purity_volume_3',' class="cell-input" name="purity_volume_3"')?></td></tr>
    <tr class="row-h-28"><td class="label">Purity (%)</td><td><?=incoming_example_field('purity_result_1',' class="cell-input" name="purity_result_1"')?></td><td><?=incoming_example_field('purity_result_2',' class="cell-input" name="purity_result_2"')?></td><td><?=incoming_example_field('purity_result_3',' class="cell-input" name="purity_result_3"')?></td></tr>
    <tr class="row-h-28"><td class="label gray">Average (%)</td><td colspan="3" class="gray"><?=incoming_example_field('purity_average',' readonly class="cell-input" name="purity_average"')?></td></tr>
  </table>

  <div class="disposition">
    <span class="lead">Disposition (/)&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;:</span>
    <label class="check-item">Pass <?=incoming_example_field('disp_pass',' type="checkbox" name="disp_pass"')?></label>
    <label class="check-item">Fail <?=incoming_example_field('disp_fail',' type="checkbox" name="disp_fail"')?></label>
    <label class="check-item">Accept <?=incoming_example_field('disp_accept',' type="checkbox" name="disp_accept"')?></label>
    <label class="check-item">Accept with LOC <?=incoming_example_field('disp_accept_loc',' type="checkbox" name="disp_accept_loc"')?></label>
    <label class="check-item">Reject with LOC <?=incoming_example_field('disp_reject_loc',' type="checkbox" name="disp_reject_loc"')?></label>
  </div>

  <div class="signoff" style="margin-top:18px">
    <div>
      <div class="box"><span>Tested&nbsp;&nbsp;&nbsp;&nbsp;by</span><span>:</span><?=incoming_example_field('tested_by',' class="line-input" name="tested_by"')?></div>
      <div class="box"><span>Position</span><span>:</span><span>Lab Assistant, QA</span></div>
    </div>
    <div>
      <div class="box"><span>Verified by</span><span>:</span><?=incoming_example_field('verified_by',' class="line-input" name="verified_by"')?></div>
      <div class="box"><span>Position</span><span>:</span><span>Officer, QA</span></div>
      <div class="box"><span>Date</span><span>:</span><?=incoming_example_field('verified_date',' class="line-input" name="verified_date"')?></div>
    </div>
  </div>

  <div class="revision">Rev: 0 (LA F46)</div>
</section>

</div>
</div>
