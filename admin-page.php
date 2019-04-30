<?php if ( ! defined('ABSPATH')) {
    exit;
}

?>

<div class="wrap">

    <h1><?php _e('WP Object Cache', 'redis-cache'); ?></h1>

    <div class="section-overview">

        <?php if (function_exists('wp_object_cache_get_stats')): ?>

            <h2 class="title"><?php _e('Overview', 'redis-cache'); ?></h2>
            <?php

            $object_cache_data = wp_object_cache_get_stats();

            $data = $object_cache_data->getData();

            $rawData = $object_cache_data->getRawData();

            ?>

            <table class="form-table">

                <tr>
                    <th><?php _e('Info:', 'redis-cache'); ?></th>
                    <td><code><?php echo $object_cache_data->getInfo(); ?></code></td>
                </tr>
                <tr>
                    <th><?php _e('Size:', 'redis-cache'); ?></th>
                    <td><code><?php echo $object_cache_data->getSize(); ?></code></td>
                </tr>

                <?php if ( ! empty($data)): ?>
                    <tr>
                        <th><?php _e('Data:', 'redis-cache'); ?></th>
                        <td><code><?php var_export($data) ?></code></td>
                    </tr>
                <?php endif; ?>

                <?php if ( ! empty($rawData)): ?>
                    <tr>
                        <th><?php _e('Raw Data:', 'redis-cache'); ?></th>
                        <td><code><?php var_export($rawData) ?></code></td>
                    </tr>
                <?php endif; ?>

            </table>
        <?php endif; ?>

        <p class="submit">

            <?php if ($this->getCacheStatus()) : ?>

                <?php echo $this->getLink('flush-cache', 'Flush Cache', [
                    'button',
                    'button-primary',
                    'button-large',
                ]) ?>
            <?php endif; ?>

            <?php if ( ! $this->objectCacheDropinExists()) : ?>
                <?php echo $this->getLink('enable-cache', 'Enable Object Cache', [
                    'button',
                    'button-primary',
                    'button-large',
                ]) ?>

            <?php elseif ($this->validateObjectCacheDropin()) : ?>
                <?php echo $this->getLink('disable-cache', 'Disable Object Cache', [
                    'button',
                    'button-secondary',
                    'button-large',
                    'delete',
                ]) ?>
            <?php endif; ?>

        </p>

    </div>
</div>
