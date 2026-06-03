<x-layout title="Privacy Policy | DevSense" description="Privacy Policy, cookies, and GDPR compliance agreements for DevSense">
<main class="legal-page">
    <div class="legal-container">
        <div class="legal-card">
            @if(app()->getLocale() === 'ru')
                <h1 class="legal-title">Политика конфиденциальности</h1>
                <p class="legal-date">Последнее обновление: 3 июня 2026 г.</p>

                <div class="legal-content">
                    <p>Настоящая Политика конфиденциальности регулирует сбор, обработку, использование и защиту ваших персональных данных администрацией платформы <strong>DevSense</strong> (далее — «Платформа», «Мы»). Мы стремимся защищать вашу конфиденциальность и обрабатываем данные в строгом соответствии с применимым законодательством, включая Общий регламент по защите данных ЕС (GDPR).</p>

                    <h2>1. Администратор (Контроллер) данных</h2>
                    <p>Контроллером ваших персональных данных является Администрация DevSense. По любым вопросам, касающимся обработки, изменения или удаления ваших данных, вы можете связаться с нами по электронной почте: <a href="mailto:privacy@devsense.work" class="auth-link">privacy@devsense.work</a>.</p>

                    <h2>2. Какую информацию мы собираем</h2>
                    <p>Мы собираем информацию следующих категорий:</p>
                    <ul>
                        <li><strong>Данные учетной записи автора:</strong> при регистрации в качестве автора мы запрашиваем ваше полное имя, адрес электронной почты, пароль (хранится в зашифрованном виде посредством одностороннего хеширования) и назначенную роль.</li>
                        <li><strong>Данные профиля автора:</strong> вы можете по своему желанию указать должность (job title), биографию (bio), ссылки на профили в социальных сетях (GitHub, LinkedIn) и загрузить изображение профиля (аватар).</li>
                        <li><strong>Технические данные (логи сервера):</strong> IP-адрес, тип и версия браузера, языковые настройки, время и дата посещения, просмотренные страницы, реферер (источник перехода) и поисковые запросы.</li>
                        <li><strong>Данные аналитики и кэширования:</strong> информация о взаимодействии с контентом, собираемая через локальные кэш-системы (Redis/Meilisearch) и внешние аналитические инструменты (Google Tag Manager / Google Analytics при их активации в продакшн-окружении).</li>
                        <li><strong>Жалобы и обращения:</strong> при отправке жалобы на контент мы фиксируем ваш IP-адрес, выбранную причину жалобы и предоставленные вами текстовые подробности.</li>
                    </ul>

                    <h2>3. Правовые основания и цели обработки данных</h2>
                    <p>Мы обрабатываем ваши персональные данные на следующих основаниях:</p>
                    <ul>
                        <li><strong>Исполнение соглашения (договора):</strong> создание личного кабинета автора, публикация ваших статей под вашим именем, управление видимостью вашего профиля.</li>
                        <li><strong>Согласие пользователя:</strong> обработка дополнительных данных профиля (ссылки на соцсети, био), использование файлов cookie для хранения ваших предпочтений (язык, тема оформления), а также подписка на обновления.</li>
                        <li><strong>Законный интерес Платформы:</strong> защита сайта от спама, автоматических ботов и мошенничества, обеспечение безопасности баз данных, модерация контента, анализ трафика для улучшения качества публикуемых инженерных гайдов.</li>
                    </ul>

                    <h2>4. Использование файлов Cookie и локального хранилища</h2>
                    <p>DevSense использует файлы cookie и аналогичные технологии локального хранения данных (LocalStorage) для обеспечения базовой функциональности сайта:</p>
                    <ul>
                        <li><strong>Сессионные файлы cookie:</strong> необходимы для аутентификации авторов и поддержания активного сеанса.</li>
                        <li><strong>Защита CSRF:</strong> специальные cookie для предотвращения межсайтовой подделки запросов и защиты форм отправки данных.</li>
                        <li><strong>Настройки интерфейса:</strong> сохранение выбранной вами языковой локали и темы оформления (светлая/темная/nord).</li>
                    </ul>
                    <p>Вы можете отключить поддержку файлов cookie в настройках вашего браузера, однако в этом случае авторизация в личном кабинете автора станет невозможной.</p>

                    <h2>5. Передача данных третьим сторонам</h2>
                    <p>Мы не продаем и не передаем ваши персональные данные третьим лицам в маркетинговых целях. Передача данных осуществляется исключительно для обеспечения работоспособности технических сервисов:</p>
                    <ul>
                        <li><strong>Облачные провайдеры:</strong> хостинг баз данных PostgreSQL и хранилище медиафайлов (картинок) DigitalOcean Spaces (S3-совместимое хранилище). Изображения профилей и статей загружаются на внешние защищенные серверы.</li>
                        <li><strong>Аналитические системы:</strong> на продакшн-сервере могут использоваться анонимизированные данные в Google Analytics для отслеживания посещаемости без идентификации конкретного пользователя.</li>
                    </ul>

                    <h2>6. Безопасность и хранение данных</h2>
                    <p>Мы принимаем надлежащие технические и организационные меры для защиты ваших данных от несанкционированного доступа, изменения или уничтожения:</p>
                    <ul>
                        <li>Все пароли шифруются с использованием современных криптографических алгоритмов (bcrypt).</li>
                        <li>Передача данных между вашим браузером и сервером защищена протоколом шифрования SSL/TLS.</li>
                        <li>Медиафайлы перед загрузкой на облачное хранилище очищаются от метаданных EXIF (включая GPS-координаты и параметры устройств), чтобы предотвратить утечку личной информации.</li>
                        <li>Ваши данные хранятся до тех пор, пока ваша учетная запись автора активна, либо пока это необходимо для выполнения юридических обязательств.</li>
                    </ul>

                    <h2>7. Права пользователей (GDPR и международное право)</h2>
                    <p>Вы обладаете следующими правами в отношении ваших персональных данных:</p>
                    <ul>
                        <li><strong>Право на доступ:</strong> вы можете запросить информацию о том, какие именно данные мы храним.</li>
                        <li><strong>Право на исправление:</strong> вы можете обновить свои профильные данные в любой момент в настройках личного кабинета.</li>
                        <li><strong>Право на удаление («право быть забытым»):</strong> вы можете запросить полное удаление вашей учетной записи и всех связанных с ней данных. Для этого отправьте запрос на <a href="mailto:privacy@devsense.work" class="auth-link">privacy@devsense.work</a>.</li>
                        <li><strong>Право на ограничение обработки и переносимость данных:</strong> вы можете запросить выгрузку ваших данных в машиночитаемом формате или потребовать ограничить их обработку.</li>
                        <li><strong>Право на отзыв согласия:</strong> вы можете в любой момент отозвать свое согласие на обработку данных, что приведет к прекращению действия вашей учетной записи.</li>
                    </ul>

                    <h2>8. Изменения в Политике конфиденциальности</h2>
                    <p>Мы оставляем за собой право вносить изменения в настоящую Политику конфиденциальности. В случае существенных изменений мы уведомим вас путем публикации обновленной версии на этой странице с указанием даты последнего обновления.</p>
                </div>
            @else
                <h1 class="legal-title">Privacy Policy</h1>
                <p class="legal-date">Last updated: June 3, 2026</p>

                <div class="legal-content">
                    <p>This Privacy Policy governs the collection, processing, use, and protection of your personal data by the administration of the <strong>DevSense</strong> platform (hereinafter referred to as "Platform", "We"). We are committed to protecting your privacy and process data in strict compliance with applicable laws, including the EU General Data Protection Regulation (GDPR).</p>

                    <h2>1. Data Controller</h2>
                    <p>The controller of your personal data is the DevSense Administration. For any questions regarding the processing, modification, or erasure of your data, you can contact us at: <a href="mailto:privacy@devsense.work" class="auth-link">privacy@devsense.work</a>.</p>

                    <h2>2. Information We Collect</h2>
                    <p>We collect the following categories of information:</p>
                    <ul>
                        <li><strong>Author Account Data:</strong> when registering as an author, we request your full name, email address, password (stored securely using one-way cryptographic hashing), and assigned role.</li>
                        <li><strong>Author Profile Data:</strong> you may optionally provide your job title, biography (bio), links to social media profiles (GitHub, LinkedIn), and upload a profile picture (avatar).</li>
                        <li><strong>Technical Data (Server Logs):</strong> IP address, browser type and version, language settings, time and date of visit, pages viewed, referrer URL, and search queries.</li>
                        <li><strong>Analytics and Caching:</strong> interaction data collected via local caching mechanisms (Redis/Meilisearch) and third-party analytical tools (Google Tag Manager / Google Analytics when active in production environment).</li>
                        <li><strong>Reports and Submissions:</strong> when reporting content, we capture your IP address, selected preset reason, and the text details provided.</li>
                    </ul>

                    <h2>3. Legal Basis and Purposes of Data Processing</h2>
                    <p>We process your personal data based on the following grounds:</p>
                    <ul>
                        <li><strong>Performance of a Contract:</strong> managing your author account, publishing your articles under your authorship, and handling your profile visibility.</li>
                        <li><strong>User Consent:</strong> processing optional profile fields (biography, social links), using cookies to persist preferences (language, theme selection), and email notification preferences.</li>
                        <li><strong>Legitimate Interests:</strong> protecting the platform against spam, automated bots, and abuse, ensuring database security, content moderation, and traffic analysis to improve the quality of published technical guides.</li>
                    </ul>

                    <h2>4. Cookies and Local Storage</h2>
                    <p>DevSense uses cookies and similar local storage technologies (LocalStorage) to maintain essential site functionality:</p>
                    <ul>
                        <li><strong>Session Cookies:</strong> required for author authentication and active session maintenance.</li>
                        <li><strong>CSRF Protection:</strong> tokens to prevent cross-site request forgery and protect form submissions.</li>
                        <li><strong>Interface Settings:</strong> remembering your selected language locale and UI theme (light, dark, or nord).</li>
                    </ul>
                    <p>You can disable cookies in your browser settings, though doing so will prevent you from logging into the author dashboard.</p>

                    <h2>5. Third-Party Data Transfers</h2>
                    <p>We do not sell or lease your personal data to third parties for marketing purposes. Data transfers are strictly limited to technical service providers:</p>
                    <ul>
                        <li><strong>Cloud Providers:</strong> PostgreSQL database hosting and DigitalOcean Spaces (S3-compatible) storage for profile and article media. Uploads are stored on secure external servers.</li>
                        <li><strong>Analytics:</strong> on the production server, anonymized traffic data might be processed in Google Analytics without identifying individual users.</li>
                    </ul>

                    <h2>6. Security and Retention</h2>
                    <p>We employ appropriate technical and organizational measures to protect your data from unauthorized access, modification, or erasure:</p>
                    <ul>
                        <li>All user passwords are hashed using modern cryptographic algorithms (bcrypt).</li>
                        <li>Data transmission between your browser and the server is encrypted using SSL/TLS protocols.</li>
                        <li>Media files are stripped of metadata (including EXIF/GPS info) prior to cloud storage upload to prevent location leakage.</li>
                        <li>Your personal data is stored as long as your author account remains active, or as required by law.</li>
                    </ul>

                    <h2>7. Your Rights (GDPR &amp; International Law)</h2>
                    <p>Under GDPR, you have the following rights regarding your personal data:</p>
                    <ul>
                        <li><strong>Right of Access:</strong> you can request information on what personal data we store about you.</li>
                        <li><strong>Right to Rectification:</strong> you can update your profile information at any time in your cabinet dashboard settings.</li>
                        <li><strong>Right to Erasure ("Right to be Forgotten"):</strong> you can request the deletion of your account and all associated data. Send requests to <a href="mailto:privacy@devsense.work" class="auth-link">privacy@devsense.work</a>.</li>
                        <li><strong>Right to Restriction and Portability:</strong> you can request restriction of processing or download your account data in a structured, machine-readable format.</li>
                        <li><strong>Right to Withdraw Consent:</strong> you can withdraw your consent to data processing at any time, which will result in account termination.</li>
                    </ul>

                    <h2>8. Changes to this Privacy Policy</h2>
                    <p>We reserve the right to modify this Privacy Policy. In the event of significant changes, we will post the updated version on this page with the revised date.</p>
                </div>
            @endif
            <div class="legal-footer-link">
                <a href="{{ route('home') }}" class="auth-link">← {{ __('ui.php_show.back_to_guides') ?? 'Back to Home' }}</a>
            </div>
        </div>
    </div>
</main>

<style>
.legal-page {
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: calc(100vh - 12rem);
    padding: 2rem;
}

.legal-container {
    width: 100%;
    max-width: 800px;
}

.legal-card {
    background-color: var(--bg-color);
    border: 1px solid var(--border-color);
    border-radius: 1rem;
    padding: 3rem;
    box-shadow: 0 4px 24px rgba(0, 0, 0, 0.06);
}

.legal-title {
    font-family: 'Outfit', sans-serif;
    font-size: 2.25rem;
    font-weight: 800;
    margin-bottom: 0.5rem;
    letter-spacing: -0.5px;
    background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-hover) 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
}

.legal-date {
    font-size: 0.9rem;
    color: var(--text-muted);
    margin-bottom: 2rem;
}

.legal-content {
    color: var(--text-color);
    line-height: 1.7;
    font-size: 1.05rem;
}

.legal-content h2 {
    font-family: 'Outfit', sans-serif;
    font-size: 1.35rem;
    font-weight: 700;
    margin-top: 2rem;
    margin-bottom: 0.75rem;
    color: var(--text-color);
    border-bottom: 1px solid var(--border-color);
    padding-bottom: 0.35rem;
}

.legal-content p {
    margin-bottom: 1rem;
}

.legal-content ul {
    margin-bottom: 1.5rem;
    padding-left: 1.5rem;
}

.legal-content li {
    margin-bottom: 0.5rem;
}

.legal-footer-link {
    margin-top: 2.5rem;
    padding-top: 1.5rem;
    border-top: 1px solid var(--border-color);
}
</style>
</x-layout>
