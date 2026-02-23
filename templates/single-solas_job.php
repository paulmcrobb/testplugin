<?php
/**
 * Single Job template (plugin-enforced)
 */
defined('ABSPATH') || exit;

get_header();

while (have_posts()) :
    the_post();
    $job_id = (int) get_the_ID();

    $company  = (string) get_post_meta($job_id, 'solas_company_name', true);
    $location = (string) get_post_meta($job_id, 'solas_location', true);
    $remote   = (string) get_post_meta($job_id, 'solas_remote', true);
    $salary   = (string) get_post_meta($job_id, 'solas_salary', true);
    $contract = (string) get_post_meta($job_id, 'solas_contract_type', true);
    $hours    = (string) get_post_meta($job_id, 'solas_hours', true);

    $apply_url = function_exists('solas_jobs_portal_apply_page_url') ? solas_jobs_portal_apply_page_url($job_id) : '';

    ?>
    <main id="primary" class="site-main">
        <div class="solas-job-single" style="max-width:1000px;margin:0 auto;padding:24px 16px;">
            <a href="<?php echo esc_url(get_post_type_archive_link('solas_job')); ?>" style="display:inline-block;margin-bottom:14px;">&larr; Back to jobs</a>

            <h1 style="margin:0 0 10px 0;"><?php the_title(); ?></h1>

            <div style="opacity:.8;margin-bottom:16px;">
                <?php if ($company) : ?><strong><?php echo esc_html($company); ?></strong> &nbsp;<?php endif; ?>
                <span><?php echo esc_html(get_the_date('d/m/Y', $job_id)); ?></span>
            </div>

            <?php if (has_post_thumbnail()) : ?>
                <div style="margin:0 0 18px 0;">
                    <?php
                    // Force a sensible display size (prevents "giant" hero images)
                    echo wp_get_attachment_image(
                        get_post_thumbnail_id($job_id),
                        'large',
                        false,
                        [
                            'style' => 'max-width:520px;width:100%;height:auto;object-fit:contain;border:1px solid #eee;border-radius:12px;background:#fff;',
                            'loading' => 'lazy',
                            'decoding' => 'async',
                        ]
                    );
                    ?>
                </div>
            <?php endif; ?>

            <div style="display:flex;gap:18px;flex-wrap:wrap;margin-bottom:18px;">
                <div style="flex:1 1 320px;min-width:280px;border:1px solid #eee;border-radius:12px;padding:14px;background:#fff;">
                    <h3 style="margin:0 0 10px 0;">Job details</h3>
                    <?php if ($location) : ?><div><strong>Location:</strong> <?php echo esc_html($location); ?></div><?php endif; ?>
                    <?php if ($remote) : ?><div><strong>Remote:</strong> <?php echo esc_html($remote); ?></div><?php endif; ?>
                    <?php if ($contract) : ?><div><strong>Contract:</strong> <?php echo esc_html($contract); ?></div><?php endif; ?>
                    <?php if ($hours) : ?><div><strong>Hours:</strong> <?php echo esc_html($hours); ?></div><?php endif; ?>
                    <?php if ($salary) : ?><div><strong>Salary:</strong> <?php echo esc_html($salary); ?></div><?php endif; ?>
                </div>

                <div style="flex:1 1 240px;min-width:240px;border:1px solid #eee;border-radius:12px;padding:14px;background:#fff;">
                    <h3 style="margin:0 0 10px 0;">Apply</h3>
                    <?php if ($apply_url) : ?>
                        <a href="<?php echo esc_url($apply_url); ?>" class="<?php echo esc_attr(function_exists('solas_wc_button_classes') ? solas_wc_button_classes('primary') : 'button'); ?>">Apply for job</a>
                    <?php else : ?>
                        <p>Application link unavailable.</p>
                    <?php endif; ?>
                </div>
            </div>

            <article class="entry-content" style="border:1px solid #eee;border-radius:12px;padding:16px;background:#fff;">
                <?php the_content(); ?>
            </article>
        </div>
    </main>
    <?php
endwhile;

get_footer();
