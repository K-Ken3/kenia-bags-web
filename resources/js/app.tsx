import React from 'react';
import type { ResolvedComponent } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';
import { StoreContext, type StoreCartItem } from './Components/StoreContext';

type InertiaPageModule = {
  default: ResolvedComponent;
};

const pages = import.meta.glob<InertiaPageModule>('./Pages/**/*.tsx');

type PagePayload = {
  component: string;
  props: Record<string, unknown>;
};

function StorePage({ PageComponent, page }: { PageComponent: ResolvedComponent; page: PagePayload }) {
  const [cartCount, setCartCount] = React.useState(
    typeof page.props.cartCount === 'number' ? page.props.cartCount : 0,
  );
  const [cartPreview, setCartPreview] = React.useState<StoreCartItem[]>(
    Array.isArray(page.props.cartPreview) ? page.props.cartPreview as StoreCartItem[] : [],
  );
  const sharedProps = {
    ...page.props,
    cartCount,
    setCartCount,
    cartPreview,
    setCartPreview,
  };

  return (
    <StoreContext.Provider value={sharedProps}>
      <PageComponent {...sharedProps} />
    </StoreContext.Provider>
  );
}

async function renderPage() {
  const pageElement = document.querySelector<HTMLScriptElement>('script[data-page="app"]');
  const mountElement = document.getElementById('app');
  if (!pageElement || !mountElement) {
    throw new Error('The page payload or React mount element was not found.');
  }

  const page = JSON.parse(pageElement.textContent || '') as PagePayload;
  if (!page.component || !page.props || typeof page.props !== 'object') {
    throw new Error('The page payload is invalid.');
  }

  const loadPage = pages[`./Pages/${page.component}.tsx`];
  if (!loadPage) {
    throw new Error(`Unknown page component: ${page.component}`);
  }

  const PageComponent = (await loadPage()).default;
  createRoot(mountElement).render(<StorePage PageComponent={PageComponent} page={page} />);
}

void renderPage().catch((error: unknown) => {
  console.error('Unable to render the requested page.', error);
  throw error;
});
