<x-layout title="Terms and Conditions | DevSense" description="Terms of Service, licensing, and code disclaimers for DevSense authors and readers">
<main class="legal-page">
    <div class="legal-container">
        <div class="legal-card">
            @if(app()->getLocale() === 'ru')
                <h1 class="legal-title">Условия использования</h1>
                <p class="legal-date">Последнее обновление: 3 июня 2026 г.</p>

                <div class="legal-content">
                    <p>Добро пожаловать на платформу <strong>DevSense</strong> (далее — «Платформа», «Мы»). Пожалуйста, внимательно прочитайте настоящие Условия использования перед началом работы с сайтом. Регистрируясь или пользуясь нашими материалами, вы соглашаетесь с данными условиями в полном объеме.</p>

                    <h2>1. Общие положения и регистрация</h2>
                    <p>DevSense предоставляет открытый доступ к образовательным гайдам по веб-разработке и системному программированию. Зарегистрироваться в качестве автора может любое дееспособное лицо. Вы обязуетесь предоставлять достоверную информацию при регистрации и несете полную ответственность за сохранение конфиденциальности пароля и безопасность своего аккаунта.</p>

                    <h2>2. Лицензирование и права на контент</h2>
                    <p>Публикуя статьи, переводы, код или иные материалы на DevSense, вы соглашаетесь со следующими условиями лицензирования:</p>
                    <ul>
                        <li>Вы сохраняете авторство на свои публикации.</li>
                        <li>Вы предоставляете DevSense безотзывную, бессрочную, безвозмездную, сублицензируемую лицензию на использование, размещение, хранение, воспроизведение, изменение, форматирование, перевод на другие языки, распространение и публикацию вашего контента во всех странах мира на любых носителях.</li>
                        <li>Мы оставляем за собой право форматировать текст, исправлять опечатки, структурировать код, дополнять SEO-метаданные и оптимизировать графические файлы для повышения удобочитаемости и соответствия стандартам качества Платформы.</li>
                    </ul>

                    <h2>3. Правила публикации и научный подход</h2>
                    <p>Для поддержания высокой профессиональной планки сообщества, к публикуемому контенту предъявляются строгие требования:</p>
                    <ul>
                        <li><strong>Инженерная достоверность:</strong> материалы должны следовать строгому научно-практическому подходу. Не допускается публикация заведомо ложных инструкций, непроверенных конфигураций или псевдонаучных теорий, способных нанести ущерб инфраструктуре читателей.</li>
                        <li><strong>Интеллектуальная собственность:</strong> плагиат категорически запрещен. Копирование чужих статей без указания первоисточника и согласия автора ведет к блокировке материала.</li>
                        <li><strong>Недопустимый контент:</strong> на платформе строго запрещена дискриминация по расовому, национальному, религиозному, гендерному или возрастному признаку, а также разжигание ненависти, оскорбления, клевета или призывы к противоправным действиям.</li>
                    </ul>

                    <h2>4. Система жалоб и модерация (EU DSA / DMCA)</h2>
                    <p>На Платформе действует интерактивный механизм подачи жалоб на контент:</p>
                    <ul>
                        <li>Каждый пользователь может отправить жалобу на статью или профиль автора через специальную форму, указав основание (например, нарушение авторских прав, дискриминация, недостоверная информация).</li>
                        <li>Администрация оперативно рассматривает все поступающие жалобы. На время рассмотрения спорный контент может быть временно скрыт из публичного доступа.</li>
                        <li>Администрация DevSense имеет абсолютное и безусловное право по собственному усмотрению временно скрыть (перевести в статус черновика), окончательно удалить любой контент или заблокировать аккаунт автора за несоблюдение настоящих Условий без предварительного уведомления и объяснения причин.</li>
                    </ul>

                    <h2>5. Отказ от гарантий и ограничение ответственности</h2>
                    <p>Все материалы, статьи, советы и примеры программного кода (включая конфигурации Docker, базы данных и настройки серверов) публикуются исключительно в ознакомительных целях.</p>
                    <ul>
                        <li><strong>Принцип «Как есть» (As Is):</strong> Мы не предоставляем никаких явных или подразумеваемых гарантий относительно точности, актуальности или применимости программного кода на вашем оборудовании, серверах или проектах.</li>
                        <li><strong>Отказ от ответственности:</strong> Администрация DevSense и авторы статей не несут ответственности за любые прямые или косвенные убытки, включая, но не ограничиваясь: повреждение серверов, сбои баз данных, утечку данных, финансовые потери или упущенную выгоду, возникшие в результате применения инструкций, опубликованных на сайте.</li>
                        <li>Вы используете предоставленный код и рекомендации исключительно на свой собственный страх и риск.</li>
                    </ul>

                    <h2>6. Применимое право и разрешение споров</h2>
                    <p>Настоящие Условия регулируются в соответствии с законодательством Европейского Союза. Все споры, возникающие в связи с использованием Платформы, подлежат разрешению путем переговоров. В случае невозможности достижения согласия спор передается на рассмотрение в компетентный суд по месту нахождения администрации Платформы.</p>
                </div>
            @else
                <h1 class="legal-title">Terms &amp; Conditions</h1>
                <p class="legal-date">Last updated: June 3, 2026</p>

                <div class="legal-content">
                    <p>Welcome to the <strong>DevSense</strong> platform (hereinafter referred to as "Platform", "We"). Please read these Terms and Conditions carefully before using our website. By registering or using our materials, you agree to be bound by these terms in full.</p>

                    <h2>1. General Provisions and Registration</h2>
                    <p>DevSense provides public access to educational guides on software engineering. Anyone with full legal capacity can register as an author. You agree to provide accurate information upon registration and are solely responsible for maintaining account confidentiality and security.</p>

                    <h2>2. Content Licensing and Rights</h2>
                    <p>By publishing articles, translations, source code, or other materials on DevSense, you agree to the following licensing terms:</p>
                    <ul>
                        <li>You retain ownership of your original publications.</li>
                        <li>You grant DevSense a perpetual, irrevocable, worldwide, royalty-free, transferable, and sublicensable license to host, store, use, reproduce, modify, format, translate, distribute, and publish your content on any media.</li>
                        <li>We reserve the right to format text, fix typographical or layout issues, clean up code styling, amend SEO metadata, and optimize images to maintain the Platform's quality and readability standards.</li>
                    </ul>

                    <h2>3. Scientific Approach and Content Policies</h2>
                    <p>To ensure high technical standards, all publications must comply with the following criteria:</p>
                    <ul>
                        <li><strong>Technical Accuracy:</strong> guides must follow a rigorous, evidence-based, and engineering approach. Publishing false instructions, untested configurations, or unverified claims that could damage the technical infrastructure of readers is prohibited.</li>
                        <li><strong>Intellectual Property:</strong> plagiarism is strictly forbidden. Copying articles without explicit consent or referencing the original author will result in content removal.</li>
                        <li><strong>Prohibited Content:</strong> any form of discrimination or harassment based on race, nationality, religion, gender, or age, as well as hate speech, defamation, and incitement to illegal acts, is strictly prohibited.</li>
                    </ul>

                    <h2>4. Reporting System and Moderation (EU DSA / DMCA)</h2>
                    <p>We provide a dedicated user reporting mechanism to enforce safety and copyright standards:</p>
                    <ul>
                        <li>Users can report articles or author profiles using the report form, specifying the category of violation (e.g. copyright infringement, hate speech, pseudo-scientific content).</li>
                        <li>The administration reviews reports promptly. Contested content may be suspended from public view during review.</li>
                        <li>DevSense administration reserves the absolute, unconditional right to suspend (move to draft), delete any content, or terminate author accounts at its sole discretion, at any time and without prior notice, for breach of these Terms.</li>
                    </ul>

                    <h2>5. Disclaimer of Warranties and Limitation of Liability</h2>
                    <p>All materials, guides, tips, and programming code (including Docker configurations, database queries, and deployment scripts) are published solely for educational purposes.</p>
                    <ul>
                        <li><strong>"As Is" Basis:</strong> We make no warranties, express or implied, regarding the accuracy, completeness, or suitability of the code for your specific infrastructure, servers, or production systems.</li>
                        <li><strong>Limitation of Liability:</strong> DevSense administration and individual authors shall not be liable for any direct or indirect damages, including but not limited to server downtime, data loss, database corruption, financial losses, or loss of profits arising out of the use of guidelines published on this site.</li>
                        <li>Any implementation of code or suggestions from DevSense is carried out solely at your own risk.</li>
                    </ul>

                    <h2>6. Governing Law and Disputes</h2>
                    <p>These Terms and Conditions are governed by and construed in accordance with the laws of the European Union. Any disputes arising in connection with the Platform shall be resolved through negotiation. If unresolved, they shall be submitted to the competent court in the jurisdiction of the Platform's administration.</p>
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
