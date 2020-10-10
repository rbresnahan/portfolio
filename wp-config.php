<?php
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the
 * installation. You don't have to use the web site, you can
 * copy this file to "wp-config.php" and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * MySQL settings
 * * Secret keys
 * * Database table prefix
 * * ABSPATH
 *
 * @link https://wordpress.org/support/article/editing-wp-config-php/
 *
 * @package WordPress
 */

// ** MySQL settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', 'ryan_portfolio' );

/** MySQL database username */
define( 'DB_USER', 'root' );

/** MySQL database password */
define( 'DB_PASSWORD', '' );

/** MySQL hostname */
define( 'DB_HOST', 'localhost' );

/** Database Charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8mb4' );

/** The Database Collate type. Don't change this if in doubt. */
define( 'DB_COLLATE', '' );

/**#@+
 * Authentication Unique Keys and Salts.
 *
 * Change these to different unique phrases!
 * You can generate these using the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}
 * You can change these at any point in time to invalidate all existing cookies. This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define( 'AUTH_KEY',         'ooZ*Tj GdEEjr0-tW<S1MQTG|ulw/+[@oxxW(Owxw?>VGXHM{cm`fK%1w[3vyMJ>' );
define( 'SECURE_AUTH_KEY',  'D!x{S<g2#d;4@/7l3tCuO!zmOO4Wcv3iraNx]+NKO+v>Tu6<OC20[T5tjM9^nj=(' );
define( 'LOGGED_IN_KEY',    't8eDuE+1/8{{ PT|t:j;Wt}p.$:Lj/p_mb_M40neyij:m~<*nRnU+fc3pd&#!bWy' );
define( 'NONCE_KEY',        'rb}&<|yox@mN&uR(.x7YP<c>k#Fg6y]j7-C15~r4Wt0^h-c!vew]jr{$UE$QEKTZ' );
define( 'AUTH_SALT',        '$FU-S x=]pbpO#a<}0u_EB&lH}:W>HEiMr:je>v6hvSL-Hhj(?>P|UN]y8.`vVnN' );
define( 'SECURE_AUTH_SALT', 'w/0.,Ak(2~u u.sXArD)~BchahX!TJ+_PF7Zz 3nR>#kSIW^:anJ?CrIJ8%#@3cA' );
define( 'LOGGED_IN_SALT',   '?}I%`7 oT$V)OfjZ{kF1kXf5-|>foEe+M@t8Emb o:kSgb{>eSVWY:}Jh5W9p_GF' );
define( 'NONCE_SALT',       'fC.BgkxVSN(la<_rx Ld*-~NUd^h@F4jnihe2zA,+ROTnkAeO5M5?2Bu`ma{mb*A' );

/**#@-*/

/**
 * WordPress Database Table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */
$table_prefix = 'wp_';

/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 *
 * For information on other constants that can be used for debugging,
 * visit the documentation.
 *
 * @link https://wordpress.org/support/article/debugging-in-wordpress/
 */
define( 'WP_DEBUG', false );

/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
