<?php if (defined('ABSPATH')): ?>

    <div class="wrap">

        <h1><?php esc_html_e('WP Object Cache', $this->page_slug); ?></h1>

        <div class="section-overview">

            <?php if (function_exists('wp_object_cache_get_stats')): ?>

                <h2 class="title"><?php esc_html_e('Overview', $this->page_slug); ?></h2>
                <?php

                $object_cache_data = wp_object_cache_get_stats();

                ?>

                <table class="form-table">

                    <tr>
                        <th><?php esc_html_e('Info:', $this->page_slug); ?></th>
                        <td><code><?php var_export($object_cache_data->getInfo()); ?></code></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Size:', $this->page_slug); ?></th>
                        <td><code><?php var_export($object_cache_data->getSize()); ?></code></td>
                    </tr>

                    <tr>
                        <th><?php esc_html_e('Data:', $this->page_slug); ?></th>
                        <td><code><?php var_export($object_cache_data->getData()); ?></code></td>
                    </tr>

                    <tr>
                        <th><?php esc_html_e('Raw Data:', $this->page_slug); ?></th>
                        <td><code><?php var_export($object_cache_data->getRawData()); ?></code></td>
                    </tr>

                </table>
            <?php endif; ?>

            <p class="submit">

                <?php if ($this->getCacheStatus()) : ?>
                    <?php

                    $link_url = wp_nonce_url(network_admin_url(add_query_arg('action', 'flush-cache', $this->page)),
                        'flush-cache');

                    ?>

                    <a href="<?php echo esc_attr($link_url); ?>"
                       class="button button-primary button-large">
                        <?php esc_html_e('Flush Cache', $this->page_slug); ?>
                    </a>
                <?php endif; ?>

                <?php if (!$this->objectCacheDropinExists()) : ?>

                    <?php

                    $link_url = wp_nonce_url(network_admin_url(add_query_arg('action', 'enable-cache', $this->page)),
                        'enable-cache');

                    ?>

                    <a href="<?php echo esc_attr($link_url); ?>"
                       class="button button-primary button-large">
                        <?php esc_html_e('Enable Object Cache', $this->page_slug); ?>
                    </a>

                <?php elseif ($this->validateObjectCacheDropin()) : ?>

                    <?php

                    $link_url = wp_nonce_url(network_admin_url(add_query_arg('action', 'disable-cache', $this->page)),
                        'disable-cache');

                    ?>

                    <a href="<?php echo esc_attr($link_url); ?>"
                       class="button button-secondary button-large delete">
                        <?php esc_html_e('Disable Object Cache', $this->page_slug); ?>
                    </a>
                <?php endif; ?>

            </p>

        </div>
    </div>
<?php endif; ?>