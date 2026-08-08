<?php
/**
 * Ukrainian defaults for Freemius SDK UI (Account, opt-in, license, trial, etc.).
 *
 * Freemius ships without uk_UA; we force Ukrainian strings for this plugin.
 *
 * @package Factorial2000_Catalog_Sync
 */

defined( 'ABSPATH' ) || exit;

/**
 * Ukrainian string map for Freemius override_i18n keys.
 *
 * @return array<string, string>
 */
function f2000cs_get_freemius_uk_i18n() {
	return array(
		// Connect / opt-in.
		'opt-in-connect'                             => 'Так, увімкнути',
		'skip'                                       => 'Не зараз',
		'yes'                                        => 'Так',
		'no'                                         => 'Ні',
		'allow'                                      => 'Дозволити',
		'dont-allow'                                 => 'Не дозволяти',
		'activate'                                   => 'Активувати',
		'activate-license'                           => 'Активувати ліцензію',
		'activate-free-version'                      => 'Активувати безкоштовну версію',
		'agreement-agree'                            => 'Погоджуюсь',
		'thanks-x'                                   => 'Дякуємо, %s!',
		'welcome-x'                                  => 'Вітаємо, %s!',

		// License.
		'license'                                    => 'Ліцензія',
		'license-key'                                => 'Ключ ліцензії',
		'enter-license-key'                          => 'Введіть ключ ліцензії',
		'license-key-placeholder'                    => 'Введіть ключ ліцензії…',
		'activate-license-message'                   => 'Щоб розблокувати Pro-функції, активуйте ліцензію.',
		'license-expired'                            => 'Ліцензія закінчилась',
		'license-expired-message'                    => 'Ваша ліцензія закінчилась. Оновіть підписку, щоб знову отримати доступ до Pro.',
		'renew-license-now'                          => 'Поновити ліцензію',
		'change-license'                             => 'Змінити ліцензію',
		'deactivate-license'                         => 'Деактивувати ліцензію',
		'license-deactivated-message'                => 'Ліцензію деактивовано.',
		'verify-license'                             => 'Перевірити ліцензію',
		'license-validated'                          => 'Ліцензію підтверджено',

		// Account / billing.
		'account'                                    => 'Акаунт',
		'billing'                                    => 'Оплата',
		'payments'                                   => 'Платежі',
		'user'                                       => 'Користувач',
		'site'                                       => 'Сайт',
		'plan'                                       => 'План',
		'free'                                       => 'Безкоштовний',
		'premium'                                    => 'Premium',
		'trial'                                      => 'Тріал',
		'expires-in'                                 => 'Закінчується через %s',
		'expires-in-x'                               => 'Закінчується через %s',
		'expired-x'                                  => 'Закінчився %s',
		'unlimited'                                  => 'Необмежено',
		'download-latest'                            => 'Завантажити останню версію',
		'sync'                                       => 'Синхронізувати',
		'sync-license'                               => 'Синхронізувати ліцензію',
		'change-owner'                               => 'Змінити власника',
		'delete-account'                             => 'Видалити акаунт',
		'email'                                      => 'Email',
		'name'                                       => 'Ім’я',
		'address'                                    => 'Адреса',
		'city'                                       => 'Місто',
		'country'                                    => 'Країна',
		'state'                                      => 'Область / штат',
		'zip'                                        => 'Індекс',
		'update'                                     => 'Оновити',
		'save'                                       => 'Зберегти',
		'cancel'                                     => 'Скасувати',
		'close'                                      => 'Закрити',
		'approve'                                    => 'Підтвердити',
		'edit'                                       => 'Редагувати',
		'delete'                                     => 'Видалити',
		'id'                                         => 'ID',
		'product'                                    => 'Продукт',
		'version'                                    => 'Версія',
		'path'                                       => 'Шлях',
		'free-plan'                                  => 'Безкоштовний план',
		'premium-plan'                               => 'Платний план',

		// Account page leftovers (QA).
		'account-details'                            => 'Деталі акаунту',
		'premium-version'                            => 'Premium-версія',
		'join-beta'                                  => 'Приєднатись до Beta-програми',
		'show'                                       => 'Показати',
		'hide'                                       => 'Сховати',
		'disconnect'                                 => 'Від’єднати',
		'disconnect-intro-paid'                      => 'Від’єднання сайту назавжди прибере %s з акаунту в User Dashboard.',
		'click-here'                                 => 'Натисніть тут',
		'license_not_whitelabeled'                   => 'Це сайт клієнта? %s, якщо хочете приховати чутливі дані (email, ключ ліцензії, ціни, адресу оплати та рахунки) з WP Admin.',
		'yee-haw'                                    => 'Ура',
		'license-activated-message'                  => 'Ліцензію успішно активовано.',
		'deactivate-license-confirm'                 => 'Деактивація ліцензії заблокує всі Pro-функції, але дозволить активувати її на іншому сайті. Продовжити?',
		'license-deactivation-message'               => 'Ліцензію успішно деактивовано, ви повернулись на план %s.',
		'license-deactivation-message_premium-only'  => 'Ліцензію %s успішно деактивовано.',
		'enabling-whitelabel-mode'                   => 'Увімкнення white-label режиму',
		'disabling-whitelabel-mode'                  => 'Вимкнення white-label режиму',

		// Upgrade / pricing.
		'upgrade'                                    => 'Оновити до Pro',
		'pricing'                                    => 'Тарифи',
		'purchase'                                   => 'Купити',
		'buy-now'                                    => 'Купити зараз',
		'upgrade-now'                                => 'Оновити зараз',
		'downgrade'                                  => 'Знизити план',
		'change-plan'                                => 'Змінити план',
		'view-details'                               => 'Переглянути деталі',
		'more-information-about-x'                   => 'Більше інформації про %s',

		// Trial.
		'start-free-trial'                           => 'Почати безкоштовний тріал',
		'trial-x-promotion-message'                  => 'Дякуємо, що користуєтесь %s!',
		'already-opted-in-to-product-usage-tracking' => 'Як вам %s? Спробуйте всі Pro-функції з %d-денним тріалом.',
		'no-commitment-for-x-days'                   => 'Без зобов’язань %s днів — скасувати можна будь-коли!',
		'no-cc-required'                             => 'Банківська картка не потрібна',
		'hey'                                        => 'Привіт',
		'trial-started-message'                      => 'Тріал розпочато!',
		'trial-expired-message'                      => 'Тріал закінчився.',

		// Permissions / privacy.
		'permissions'                                => 'Дозволи',
		'privacy-policy'                             => 'Політика конфіденційності',
		'terms-of-service'                           => 'Умови використання',
		'opt-out'                                    => 'Вимкнути оновлення',
		'opt-in'                                     => 'Увімкнути оновлення',
		'have-license-key'                           => 'Маєте ключ ліцензії?',
		'dont-have-license-key'                      => 'Немає ключа ліцензії?',
		'cant-find-license-key'                      => 'Не можете знайти ключ ліцензії?',
		'send-updates'                               => 'надсилайте мені оновлення безпеки, новини та пропозиції.',
		'do-not-send-updates'                        => 'НЕ надсилайте мені оновлення безпеки, новини та пропозиції.',
		'permissions-extensions_desc'                => 'Freemius — наш рушій ліцензування та оновлень',

		// Connectivity / errors.
		'oops'                                       => 'Ой…',
		'error'                                      => 'Помилка',
		'unexpected-error'                           => 'Сталася неочікувана помилка.',
		'connectivity-issues'                        => 'Проблеми зі з’єднанням',
		'you-are-running-latest'                     => 'У вас уже остання версія.',
		'newer-x-available'                          => 'Доступна новіша версія %s',
		'new'                                        => 'Нове',
		'congrats'                                   => 'Вітаємо!',

		// Deactivation.
		'deactivation'                               => 'Деактивація',
		'deactivation-share-reason'                  => 'Будь ласка, поділіться причиною деактивації',
		'submit-and-deactivate'                      => 'Надіслати і деактивувати',
		'skip-and-deactivate'                        => 'Пропустити і деактивувати',
		'cancel-deactivation'                        => 'Скасувати деактивацію',
		'other'                                      => 'Інше',
		'i-no-longer-need-the-x'                     => 'Більше не потребую %s',
		'i-found-a-better-x'                         => 'Знайшов кращий %s',
		'whats-the-x-name'                           => 'Як називається %s?',
		'i-only-needed-the-x-for-a-short-period'     => 'Потрібен був %s лише на короткий час',
		'the-x-broke-my-site'                        => '%s зламав мій сайт',
		'the-x-suddenly-stopped-working'             => '%s раптово перестав працювати',
		'i-cant-pay-for-it-anymore'                  => 'Більше не можу за це платити',

		// Misc UI.
		'yes-im-in'                                  => 'Так, я з вами!',
		'not-today'                                  => 'Не сьогодні',
		'ok'                                         => 'OK',
		'done'                                       => 'Готово',
		'loading'                                    => 'Завантаження…',
		'please-wait'                                => 'Зачекайте…',
		'thank-you'                                  => 'Дякуємо!',
		'next'                                       => 'Далі',
		'previous'                                   => 'Назад',
		'back'                                       => 'Назад',
		'continue'                                   => 'Продовжити',
		'seats'                                      => 'Місця',
		'unlimited-seats'                            => 'Необмежена кількість місць',
		'x-left'                                     => 'Залишилось %s',
		'last-x'                                     => 'Останній %s',
		'never'                                      => 'Ніколи',
		'expired'                                    => 'Закінчився',
		'cancelled'                                  => 'Скасовано',
		'active'                                     => 'Активний',
		'inactive'                                   => 'Неактивний',
		'contact'                                    => 'Контакти',
		'contact-us'                                 => 'Зв’язатись з нами',
		'contact-us-here'                            => 'Зв’яжіться з нами тут',
		'contact-us-with-error-message'              => 'Будь ласка, напишіть нам із таким повідомленням:',
		'contact-for-updates'                        => 'Повідомте, чи хочете отримувати оновлення безпеки та функцій, навчальні матеріали та іноді пропозиції:',
		'contact-support'                            => 'Зв’язатись з підтримкою',
		'contact-support-before-deactivation'        => 'Вибачте за незручності — ми готові допомогти, якщо дасте шанс.',
		'secure-x-page-header'                       => 'Захищена HTTPS-сторінка «%s» на зовнішньому домені',
		'support'                                    => 'Підтримка',
		'support-forum'                              => 'Форум підтримки',
		'documentation'                              => 'Документація',
		'features'                                   => 'Можливості',
		'free-trial'                                 => 'Безкоштовний тріал',
		'start-trial'                                => 'Почати тріал',
		'resend-license-key'                         => 'Надіслати ключ ще раз',
		'license-key-sent-message'                   => 'Ключ ліцензії надіслано на ваш email.',
	);
}

/**
 * Apply Ukrainian Freemius UI strings for this plugin.
 *
 * @return void
 */
function f2000cs_apply_freemius_uk_i18n() {
	$fs = f2000cs_fs();
	if ( ! $fs || ! method_exists( $fs, 'override_i18n' ) ) {
		return;
	}

	$fs->override_i18n( f2000cs_get_freemius_uk_i18n() );
}
add_action( 'plugins_loaded', 'f2000cs_apply_freemius_uk_i18n', 20 );
