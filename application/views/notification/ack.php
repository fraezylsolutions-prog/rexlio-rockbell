<?php
/**
 * The acknowledge page.
 *
 * Deliberately standalone: no app layout, no stylesheet, no script. It is
 * opened from an SMS on a phone, often on a bad connection, by somebody who is
 * not signed in - so it has to render from one request with nothing else to
 * fetch. Inline CSS is the right call here and nowhere else in the product.
 *
 * $state is one of: open, already, expired, unknown.
 */
$state = isset($state) ? $state : 'unknown';
$row   = isset($row) ? $row : NULL;
$label = ($row && isset($row->label) && $row->label !== '') ? $row->label : '';
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php /* Keep this page out of search results and out of previews. */ ?>
<meta name="robots" content="noindex, nofollow, noarchive">
<meta name="referrer" content="no-referrer">
<title><?php echo lang('notify_ack_title'); ?></title>
<style>
  :root { color-scheme: light dark; }
  body { margin:0; padding:24px 16px; font:16px/1.5 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Arial,sans-serif;
         background:#f4f5f7; color:#1b1f23; }
  @media (prefers-color-scheme: dark) { body { background:#15181c; color:#e6e8ea; } .card { background:#1f2428 !important; } }
  .card { max-width:30rem; margin:0 auto; background:#fff; border-radius:10px; padding:24px;
          box-shadow:0 1px 3px rgba(0,0,0,.14); }
  h1 { margin:0 0 .5rem; font-size:1.25rem; }
  .what { margin:0 0 1.25rem; padding:12px 14px; border-left:4px solid #c8cdd2; background:rgba(127,127,127,.09);
          border-radius:4px; word-break:break-word; }
  button { display:block; width:100%; padding:16px; font-size:1.0625rem; font-weight:600; color:#fff;
           background:#1f7a3d; border:0; border-radius:8px; cursor:pointer; }
  button:active { background:#19632f; }
  .ok   { color:#1f7a3d; font-weight:600; }
  .warn { color:#8a5a00; font-weight:600; }
  .muted { color:#6b7276; font-size:.875rem; margin-top:1rem; }
</style>
</head>
<body>
<div class="card">

<?php if ($state === 'open'): ?>
  <h1><?php echo lang('notify_ack_heading'); ?></h1>
  <?php if ($label !== ''): ?><p class="what"><?php echo escape_output($label); ?></p><?php endif; ?>
  <p><?php echo lang('notify_ack_explain'); ?></p>
  <?php /* A form, not a link: the POST is what makes a link preview harmless. */ ?>
  <?php echo form_open(base_url() . 'ack/confirm'); ?>
    <input type="hidden" name="token" value="<?php echo escape_output($token); ?>">
    <button type="submit"><?php echo lang('notify_ack_button'); ?></button>
  <?php echo form_close(); ?>
  <p class="muted"><?php echo lang('notify_ack_expires'); ?>
    <?php echo escape_output(isset($row->expires_at) ? $row->expires_at : ''); ?></p>

<?php elseif ($state === 'already'): ?>
  <h1 class="ok"><?php echo lang('notify_ack_done_heading'); ?></h1>
  <?php if ($label !== ''): ?><p class="what"><?php echo escape_output($label); ?></p><?php endif; ?>
  <p><?php echo lang('notify_ack_done'); ?>
     <?php echo escape_output(isset($row->acknowledged_at) ? $row->acknowledged_at : ''); ?>.</p>
  <p class="muted"><?php echo lang('notify_ack_done_note'); ?></p>

<?php elseif ($state === 'expired'): ?>
  <h1 class="warn"><?php echo lang('notify_ack_expired_heading'); ?></h1>
  <?php if ($label !== ''): ?><p class="what"><?php echo escape_output($label); ?></p><?php endif; ?>
  <p><?php echo lang('notify_ack_expired'); ?></p>

<?php else: ?>
  <?php /* Unknown and malformed tokens get the SAME answer, so the page cannot
           be used to tell a real token from a typo. */ ?>
  <h1 class="warn"><?php echo lang('notify_ack_unknown_heading'); ?></h1>
  <p><?php echo lang('notify_ack_unknown'); ?></p>
<?php endif; ?>

</div>
</body>
</html>
