'use client';

import { useState, useEffect, useRef } from 'react';
import api from '@/lib/api';
import toast from 'react-hot-toast';
import { Clock, ChefHat, X } from 'lucide-react';
import type { Order, OrderItem } from '@/lib/types';

const STATUS_CONFIG = {
  pending: { label: 'Waiting', color: 'bg-yellow-500', border: 'border-yellow-300', text: 'text-yellow-700', bg: 'bg-yellow-50' },
  preparing: { label: 'Preparing', color: 'bg-blue-500', border: 'border-blue-300', text: 'text-blue-700', bg: 'bg-blue-50' },
  ready: { label: 'Ready', color: 'bg-green-500', border: 'border-green-300', text: 'text-green-700', bg: 'bg-green-50' },
  served: { label: 'Delivered', color: 'bg-purple-500', border: 'border-purple-300', text: 'text-purple-700', bg: 'bg-purple-50' },
} as const;

type KitchenStatus = keyof typeof STATUS_CONFIG;

const NEXT_STATUS: Record<string, KitchenStatus | null> = {
  pending: 'preparing',
  preparing: 'ready',
  ready: 'served',
  served: null,
};


export default function KitchenDisplayPage() {
  const [orders, setOrders] = useState<Order[]>([]);
  const [counts, setCounts] = useState<Record<string, number>>({});
  const [loading, setLoading] = useState(true);
  const [activeTab, setActiveTab] = useState<KitchenStatus>('pending');
  const [activeItemId, setActiveItemId] = useState<number | null>(null);
  const [updating, setUpdating] = useState<number | null>(null);
  const popoverRef = useRef<HTMLDivElement>(null);

  const fetchOrders = async () => {
    try {
      const { data } = await api.get(`/kitchen/orders?status=pending,preparing,ready,served`);
      setOrders(data.orders || []);
      setCounts(data.counts || {});
    } catch {
      console.error('Failed to fetch kitchen orders');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchOrders();
    const interval = setInterval(fetchOrders, 5000);
    return () => clearInterval(interval);
  }, []);

  // Close popover on click outside
  useEffect(() => {
    const handleClick = (e: MouseEvent) => {
      if (popoverRef.current && !popoverRef.current.contains(e.target as Node)) {
        setActiveItemId(null);
      }
    };
    document.addEventListener('mousedown', handleClick);
    return () => document.removeEventListener('mousedown', handleClick);
  }, []);

  // Filter orders to only show those with items matching the active tab
  const filteredOrders = orders
    .map((order) => ({
      ...order,
      items: (order.items || []).filter((item) => (item.status || 'pending') === activeTab),
    }))
    .filter((order) => order.items.length > 0);

  const updateItemStatus = async (itemId: number, status: KitchenStatus) => {
    setUpdating(itemId);
    try {
      const { data } = await api.patch(`/order-items/${itemId}/status`, { status });
      // Update the order in local state
      setOrders((prev) =>
        prev.map((o) => (o.id === data.order.id ? data.order : o))
      );
      setActiveItemId(null);
      toast.success(`Item marked as ${STATUS_CONFIG[status].label}`);
    } catch {
      toast.error('Failed to update item');
    } finally {
      setUpdating(null);
    }
  };

  const getTimeSince = (dateStr: string) => {
    const minutes = Math.floor((Date.now() - new Date(dateStr).getTime()) / 60000);
    if (minutes < 1) return 'Just now';
    if (minutes < 60) return `${minutes}m`;
    return `${Math.floor(minutes / 60)}h ${minutes % 60}m`;
  };

  const getCardBorder = (status: KitchenStatus) => {
    return STATUS_CONFIG[status].border;
  };

  if (loading) {
    return (
      <div className="flex items-center justify-center h-64">
        <div className="w-8 h-8 border-4 border-brand border-t-transparent rounded-full animate-spin" />
      </div>
    );
  }

  return (
    <div className="h-full flex flex-col">
      {/* Header + Tabs */}
      <div className="shrink-0 mb-4">
        <div className="flex items-center gap-3 mb-3">
          <ChefHat size={24} className="text-brand" />
          <h1 className="text-xl font-bold text-gray-900">Kitchen Display</h1>
          <span className="ml-auto text-xs text-gray-400">Auto-refreshes 5s</span>
        </div>

        <div className="flex flex-wrap items-center gap-2">
          {(Object.keys(STATUS_CONFIG) as KitchenStatus[]).map((status) => {
            const config = STATUS_CONFIG[status];
            const count = counts[status] || 0;
            const isActive = activeTab === status;
            return (
              <button
                key={status}
                onClick={() => setActiveTab(status)}
                className={`px-3 py-1.5 rounded-lg text-sm font-medium transition-all flex items-center gap-1.5 ${
                  isActive
                    ? `${config.bg} ${config.text} ring-2 ring-current`
                    : `${config.bg} ${config.text} opacity-50 hover:opacity-80`
                }`}
              >
                <div className={`w-2 h-2 rounded-full ${config.color}`} />
                {config.label}
                <span className="text-xs px-1.5 py-0.5 rounded-full bg-white/60">
                  {count}
                </span>
              </button>
            );
          })}

        </div>
      </div>

      {/* Order Cards Grid */}
      <div className="flex-1 overflow-y-auto">
        <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4 gap-4">
          {filteredOrders.map((order) => (
            <div
              key={order.id}
              className={`bg-white rounded-xl border-2 ${getCardBorder(activeTab)} p-4 flex flex-col`}
            >
              {/* Order Header */}
              <div className="flex justify-between items-center mb-3">
                <div>
                  <span className="font-bold text-lg">#{order.order_number}</span>
                  <span className="text-xs text-gray-500 ml-2">
                    {order.type.replace('_', ' ')}
                    {order.table && ` — ${order.table.name}`}
                  </span>
                </div>
                <div className="flex items-center gap-1 text-xs text-gray-400">
                  <Clock size={12} />
                  {getTimeSince(order.created_at)}
                </div>
              </div>

              {/* Items (only those matching active tab) */}
              <div className="space-y-1.5 flex-1">
                {order.items?.map((item) => {
                  const itemStatus = (item.status || 'pending') as KitchenStatus;
                  const config = STATUS_CONFIG[itemStatus];
                  const nextStatus = NEXT_STATUS[itemStatus];
                  const isPopoverOpen = activeItemId === item.id;

                  return (
                    <div key={item.id} className="relative">
                      <button
                        onClick={() => setActiveItemId(isPopoverOpen ? null : item.id)}
                        className={`w-full text-left p-2 rounded-lg hover:bg-gray-50 transition-colors ${isPopoverOpen ? 'bg-gray-50 ring-1 ring-brand' : ''}`}
                      >
                        <div className="flex items-center gap-2">
                          <div className={`w-2.5 h-2.5 rounded-full shrink-0 ${config.color}`} />
                          <span className="font-bold text-brand text-sm w-5">{item.quantity}x</span>
                          <span className="text-gray-900 text-sm font-medium flex-1 truncate">{item.product_name}</span>
                        </div>
                        {item.addons && item.addons.length > 0 && (
                          <div className="ml-7 flex flex-wrap gap-1 mt-1">
                            {item.addons.map((addon, i) => (
                              <span key={i} className="text-[10px] bg-blue-50 text-blue-600 px-1.5 py-0.5 rounded">
                                + {addon.name}
                              </span>
                            ))}
                          </div>
                        )}
                        {item.special_instructions && (
                          <p className="ml-7 text-[11px] text-red-500 italic mt-0.5">
                            {`"${item.special_instructions}"`}
                          </p>
                        )}
                      </button>

                      {/* Status advance popover */}
                      {isPopoverOpen && (
                        <div
                          ref={popoverRef}
                          className="absolute left-0 right-0 top-full z-10 mt-1 bg-white border border-gray-200 rounded-lg shadow-lg p-3"
                        >
                          <div className="flex justify-between items-center mb-2">
                            <span className="text-sm font-medium text-gray-900 truncate">{item.product_name}</span>
                            <button onClick={() => setActiveItemId(null)} className="text-gray-400 hover:text-gray-600">
                              <X size={14} />
                            </button>
                          </div>
                          {nextStatus ? (
                            <button
                              onClick={() => updateItemStatus(item.id, nextStatus)}
                              disabled={updating === item.id}
                              className={`w-full py-2 rounded-lg text-sm font-medium text-white transition-colors disabled:opacity-50 ${
                                STATUS_CONFIG[nextStatus].color
                              } hover:opacity-90`}
                            >
                              {updating === item.id
                                ? 'Updating...'
                                : `Mark as ${STATUS_CONFIG[nextStatus].label}`}
                            </button>
                          ) : (
                            <p className="text-center text-sm text-gray-400 py-1">Delivered</p>
                          )}
                        </div>
                      )}
                    </div>
                  );
                })}
              </div>
            </div>
          ))}
        </div>

        {filteredOrders.length === 0 && (
          <div className="flex flex-col items-center justify-center py-16 text-gray-400">
            <ChefHat size={48} className="mb-3 opacity-30" />
            <p className="text-lg">No {STATUS_CONFIG[activeTab].label.toLowerCase()} items</p>
            <p className="text-sm">Items will appear here when their status changes</p>
          </div>
        )}
      </div>
    </div>
  );
}
