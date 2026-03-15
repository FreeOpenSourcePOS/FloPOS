'use client';

import { Search, SlidersHorizontal } from 'lucide-react';
import type { Category, Product } from '@/lib/types';
import { useCartStore } from '@/store/cart';
import { usePosSettingsStore } from '@/store/pos-settings';
import TagBadge, { firstTagBg } from './DietaryBadge';

interface Props {
  categories: Category[];
  products: Product[];
  selectedCategory: number | null;
  setSelectedCategory: (id: number | null) => void;
  search: string;
  setSearch: (s: string) => void;
  currency: string;
  onProductClick: (product: Product) => void;
}

export default function ProductGrid({
  categories, products, selectedCategory, setSelectedCategory,
  search, setSearch, currency, onProductClick,
}: Props) {
  const cart = useCartStore();
  const { showProductImages } = usePosSettingsStore();

  const filtered = products.filter((p) => {
    const matchCat = !selectedCategory || p.category_id === selectedCategory;
    const matchSearch = !search || p.name.toLowerCase().includes(search.toLowerCase());
    return matchCat && matchSearch;
  });

  return (
    <div className="flex-1 flex flex-col min-w-0 h-full overflow-hidden">
      <div className="shrink-0 mb-4">
        <div className="relative mb-3">
          <Search size={18} className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" />
          <input
            type="text"
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            placeholder="Search products..."
            className="w-full pl-10 pr-4 py-2.5 bg-white border border-gray-200 rounded-lg focus:ring-2 focus:ring-brand focus:border-brand outline-none"
          />
        </div>
        <div className="flex gap-2 overflow-x-auto pb-1 scrollbar-none">
          <button
            onClick={() => setSelectedCategory(null)}
            className={`px-4 py-2 rounded-lg text-sm font-medium whitespace-nowrap transition-colors ${
              !selectedCategory ? 'bg-brand text-white' : 'bg-white text-gray-700 border border-gray-200 hover:bg-gray-50'
            }`}
          >
            All
          </button>
          {categories.map((cat) => (
            <button
              key={cat.id}
              onClick={() => setSelectedCategory(cat.id)}
              className={`px-4 py-2 rounded-lg text-sm font-medium whitespace-nowrap transition-colors ${
                selectedCategory === cat.id ? 'bg-brand text-white' : 'bg-white text-gray-700 border border-gray-200 hover:bg-gray-50'
              }`}
            >
              {cat.name}
            </button>
          ))}
        </div>
      </div>

      <div className="flex-1 overflow-y-auto pb-20 md:pb-0">
        <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
          {filtered.map((product) => {
            const inCart = cart.items.find((i) => i.product.id === product.id);
            const bgColor = firstTagBg(product.tags);

            return (
              <button
                key={product.id}
                onClick={() => onProductClick(product)}
                className="bg-white rounded-xl p-4 border border-gray-100 hover:border-brand/40 hover:shadow-md transition-all text-left relative group"
              >
                {inCart && (
                  <span className="absolute -top-2 -right-2 bg-brand text-white text-xs w-6 h-6 rounded-full flex items-center justify-center font-bold z-10">
                    {inCart.quantity}
                  </span>
                )}

                {showProductImages && (
                  <div className={`w-full aspect-square ${bgColor} rounded-lg mb-3 flex items-center justify-center text-3xl relative`}>
                    {product.image_url ? (
                      <img src={product.image_url} alt={product.name} className="w-full h-full object-cover rounded-lg" />
                    ) : (
                      <span className="text-2xl font-bold text-gray-300">
                        {product.name.charAt(0)}
                      </span>
                    )}
                    {product.tags && product.tags.length > 0 && (
                      <span className="absolute top-1.5 left-1.5">
                        <TagBadge tag={product.tags[0]} />
                      </span>
                    )}
                  </div>
                )}

                <div className="flex items-center gap-1.5">
                  {!showProductImages && product.tags && product.tags.length > 0 && (
                    <span className="shrink-0">
                      <TagBadge tag={product.tags[0]} />
                    </span>
                  )}
                  <h3 className="font-medium text-gray-900 text-sm truncate">{product.name}</h3>
                </div>
                <div className="flex items-center justify-between mt-1">
                  <p className="text-brand font-bold">
                    {currency}{Number(product.price).toLocaleString()}
                  </p>
                  {product.addon_groups && product.addon_groups.length > 0 && (
                    <span className="text-gray-400" title="Customisable">
                      <SlidersHorizontal size={12} />
                    </span>
                  )}
                </div>
              </button>
            );
          })}
        </div>
      </div>
    </div>
  );
}
