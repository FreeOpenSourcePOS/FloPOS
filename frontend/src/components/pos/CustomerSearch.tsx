'use client';

import { useState, useRef, useEffect } from 'react';
import api from '@/lib/api';
import { useCartStore } from '@/store/cart';
import { usePosSettingsStore } from '@/store/pos-settings';
import { X, UserPlus, Search } from 'lucide-react';
import toast from 'react-hot-toast';
import type { Customer } from '@/lib/types';

interface Props {
  onSelected?: () => void;
  variant?: 'default' | 'topbar';
}

export default function CustomerSearch({ onSelected, variant = 'default' }: Props = {}) {
  const cart = useCartStore();
  const { phoneDigits } = usePosSettingsStore();
  const [query, setQuery] = useState('');
  const [results, setResults] = useState<Customer[]>([]);
  const [showDropdown, setShowDropdown] = useState(false);
  const [showCreate, setShowCreate] = useState(false);
  const [newName, setNewName] = useState('');
  const [newPhone, setNewPhone] = useState('');
  const [newAddress, setNewAddress] = useState('');
  const [creating, setCreating] = useState(false);
  const wrapperRef = useRef<HTMLDivElement>(null);
  const debounceRef = useRef<ReturnType<typeof setTimeout>>(undefined);

  const customer = cart.customer;

  // Auto-fetch customer when customerId is set but customer object is missing (e.g. held order restore)
  useEffect(() => {
    if (cart.customerId && !cart.customer) {
      api.get(`/customers/${cart.customerId}`)
        .then(res => cart.setCustomer(res.data.customer))
        .catch(() => {});
    }
  // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [cart.customerId]);

  useEffect(() => {
    const handleClickOutside = (e: MouseEvent) => {
      if (wrapperRef.current && !wrapperRef.current.contains(e.target as Node)) {
        setShowDropdown(false);
        setShowCreate(false);
      }
    };
    document.addEventListener('mousedown', handleClickOutside);
    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, []);

  const searchCustomers = (q: string) => {
    if (q.length < 2) { setResults([]); return; }
    clearTimeout(debounceRef.current);
    debounceRef.current = setTimeout(async () => {
      try {
        const { data } = await api.get(`/customers-search?q=${encodeURIComponent(q)}`);
        setResults(data.customers || []);
      } catch { setResults([]); }
    }, 300);
  };

  const handleSelect = (c: Customer) => {
    cart.setCustomer(c);
    setShowDropdown(false);
    setQuery('');
    onSelected?.();
  };

  const handleClear = () => {
    cart.setCustomer(null);
  };

  const handleCreate = async () => {
    if (!newName.trim() || !newPhone.trim()) return;
    const digitsOnly = newPhone.replace(/\D/g, '');
    if (digitsOnly.length !== phoneDigits) {
      toast.error(`Phone number must be exactly ${phoneDigits} digits`);
      return;
    }
    setCreating(true);
    try {
      const { data } = await api.post('/customers', { name: newName, phone: digitsOnly, country_code: '+91', address: newAddress || undefined });
      handleSelect(data.customer);
      setShowCreate(false);
      setNewName('');
      setNewPhone('');
      setNewAddress('');
      toast.success('Customer created');
    } catch (err: unknown) {
      const error = err as { response?: { data?: { errors?: Record<string, string[]>; message?: string } } };
      const msg = error.response?.data?.message || Object.values(error.response?.data?.errors || {}).flat().join(', ') || 'Failed to create customer';
      toast.error(msg);
    } finally {
      setCreating(false);
    }
  };

  if (customer) {
    if (variant === 'topbar') {
      return (
        <div className="flex items-center gap-2 px-3 py-1.5 bg-brand-light rounded-lg text-sm min-w-0 w-full">
          <div className="flex-1 min-w-0 flex items-center gap-x-2 gap-y-0 flex-wrap">
            <span className="font-semibold text-brand truncate">{customer.name}</span>
            <span className="text-brand/70 text-xs shrink-0">{customer.phone}</span>
            {customer.visits_count > 0 && (
              <span className="text-xs bg-white/60 text-brand px-1.5 py-0.5 rounded-full shrink-0 hidden sm:inline">
                {customer.visits_count} visits
              </span>
            )}
          </div>
          <button onClick={handleClear} className="text-brand hover:text-brand-hover shrink-0 ml-auto">
            <X size={14} />
          </button>
        </div>
      );
    }

    return (
      <div className="flex items-center justify-between px-3 py-2 bg-brand-light rounded-lg text-sm">
        <span className="font-medium text-brand truncate">{customer.name}</span>
        <button onClick={handleClear} className="text-brand hover:text-brand-hover ml-2 shrink-0">
          <X size={14} />
        </button>
      </div>
    );
  }

  return (
    <div ref={wrapperRef} className="relative">
      <div className="relative">
        <Search size={14} className="absolute left-2.5 top-1/2 -translate-y-1/2 text-gray-400" />
        <input
          type="text"
          value={query}
          onChange={(e) => { setQuery(e.target.value); searchCustomers(e.target.value); setShowDropdown(true); }}
          onFocus={() => setShowDropdown(true)}
          placeholder="Phone or name..."
          className="w-full pl-8 pr-3 py-2 text-sm border border-gray-200 rounded-lg focus:ring-2 focus:ring-brand focus:border-brand outline-none"
        />
      </div>

      {showDropdown && (query.length >= 2 || showCreate) && (
        <div className="absolute z-20 top-full left-0 right-0 mt-1 bg-white border border-gray-200 rounded-lg shadow-lg max-h-48 overflow-y-auto">
          {results.map((c) => (
            <button
              key={c.id}
              onClick={() => handleSelect(c)}
              className="w-full text-left px-3 py-2 hover:bg-gray-50 text-sm border-b border-gray-50 last:border-0"
            >
              <span className="font-medium">{c.name}</span>
              <span className="text-gray-400 ml-2">{c.phone}</span>
            </button>
          ))}
          {results.length === 0 && query.length >= 2 && !showCreate && (
            <div className="px-3 py-2 text-sm text-gray-400">No results</div>
          )}
          {!showCreate && (
            <button
              onClick={() => {
                setShowCreate(true);
                // Auto-populate phone if query looks like a number
                if (/^\d+$/.test(query.trim())) {
                  setNewPhone(query.trim());
                }
              }}
              className="w-full text-left px-3 py-2 hover:bg-gray-50 text-sm text-brand font-medium flex items-center gap-1.5 border-t border-gray-100"
            >
              <UserPlus size={14} /> New Customer
            </button>
          )}
          {showCreate && (
            <div className="p-3 border-t border-gray-100 space-y-2">
              <input
                type="text" placeholder="Name" value={newName} onChange={(e) => setNewName(e.target.value)}
                className="w-full px-3 py-1.5 text-sm border border-gray-200 rounded-lg outline-none focus:ring-1 focus:ring-brand"
              />
              <input
                type="text" placeholder={`Phone (${phoneDigits} digits)`} value={newPhone}
                onChange={(e) => setNewPhone(e.target.value.replace(/\D/g, '').slice(0, phoneDigits))}
                maxLength={phoneDigits}
                className="w-full px-3 py-1.5 text-sm border border-gray-200 rounded-lg outline-none focus:ring-1 focus:ring-brand"
              />
              <input
                type="text" placeholder="Address (optional)" value={newAddress}
                onChange={(e) => setNewAddress(e.target.value)}
                className="w-full px-3 py-1.5 text-sm border border-gray-200 rounded-lg outline-none focus:ring-1 focus:ring-brand"
              />
              <button
                onClick={handleCreate} disabled={creating || !newName.trim() || !newPhone.trim()}
                className="w-full py-1.5 bg-brand text-white text-sm rounded-lg hover:bg-brand-hover disabled:opacity-50"
              >
                {creating ? 'Creating...' : 'Create'}
              </button>
            </div>
          )}
        </div>
      )}
    </div>
  );
}
