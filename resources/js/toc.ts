/**
 * Dynamic Table of Contents generator and scroll spy.
 */
export function initTableOfContents(): void {
    const tocContainer = document.getElementById('article-toc');
    const tocMobileContainer = document.getElementById('article-toc-mobile');
    const articleContent = document.querySelector('.article__content');

    if (!articleContent || (!tocContainer && !tocMobileContainer)) {
        return;
    }

    // Find all h2 and h3 headers in markdown content
    const headings = Array.from(articleContent.querySelectorAll('h2, h3'));

    if (headings.length === 0) {
        if (tocContainer) {
            tocContainer.style.display = 'none';
        }
        const mobileToc = document.querySelector('.mobile-toc') as HTMLElement;
        if (mobileToc) {
            mobileToc.style.display = 'none';
        }
        return;
    }

    // Create the ToC list
    const makeList = (): HTMLUListElement => {
        const ul = document.createElement('ul');
        ul.className = 'sticky-toc__list';
        return ul;
    };

    const tocListDesktop = makeList();
    const tocListMobile = makeList();

    headings.forEach((heading: Element, index: number) => {
        const htmlHeading = heading as HTMLElement;
        // Generate an ID if it does not exist
        if (!htmlHeading.id) {
            const cleanText = (htmlHeading.textContent || '')
                .toLowerCase()
                .replace(/[^\w\s-]/g, '')
                .replace(/\s+/g, '-');
            htmlHeading.id = `${cleanText}-${index}`;
        }

        const isH3 = htmlHeading.tagName.toLowerCase() === 'h3';
        const text = htmlHeading.textContent || '';
        const id = htmlHeading.id;

        const makeItem = (className: string): HTMLLIElement => {
            const li = document.createElement('li');
            li.className = `sticky-toc__item ${isH3 ? 'sticky-toc__item--h3' : ''}`;

            const a = document.createElement('a');
            a.href = `#${id}`;
            a.className = className;
            a.textContent = text;

            // Handle smooth scroll manually for nice offset
            a.addEventListener('click', (e: Event) => {
                e.preventDefault();
                const target = document.getElementById(id);
                if (target) {
                    const offset = 90; // offset for sticky header
                    const bodyRect = document.body.getBoundingClientRect().top;
                    const elementRect = target.getBoundingClientRect().top;
                    const elementPosition = elementRect - bodyRect;
                    const offsetPosition = elementPosition - offset;

                    window.scrollTo({
                        top: offsetPosition,
                        behavior: 'smooth'
                    });

                    // Update URL hash without jumping
                    history.pushState(null, '', `#${id}`);
                }
            });

            li.appendChild(a);
            return li;
        };

        if (tocContainer) {
            tocListDesktop.appendChild(makeItem('sticky-toc__link'));
        }
        if (tocMobileContainer) {
            tocListMobile.appendChild(makeItem('mobile-toc__link sticky-toc__link'));
        }
    });

    if (tocContainer) {
        tocContainer.appendChild(tocListDesktop);
    }
    if (tocMobileContainer) {
        tocMobileContainer.appendChild(tocListMobile);
    }

    // Scroll spy logic using IntersectionObserver
    const observerOptions = {
        root: null,
        rootMargin: '-10% 0px -75% 0px', // focused in top-middle area of viewport
        threshold: 0
    };

    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (entry.isIntersecting) {
                const id = entry.target.id;
                // Remove active from all
                document.querySelectorAll('.sticky-toc__link').forEach((link) => {
                    link.classList.remove('active');
                });

                // Add active to current header links
                const activeLinks = document.querySelectorAll(`.sticky-toc__link[href="#${id}"]`);
                activeLinks.forEach((link) => {
                    link.classList.add('active');
                });
            }
        });
    }, observerOptions);

    headings.forEach((heading) => {
        observer.observe(heading);
    });
}
