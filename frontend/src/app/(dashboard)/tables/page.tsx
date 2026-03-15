'use client';

import { useState, useEffect } from 'react';
import api from '@/lib/api';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import toast from 'react-hot-toast';
import { Plus, X } from 'lucide-react';
import type { Table } from '@/lib/types';

const statusColors: Record<string, string> = {
  available: 'bg-green-500',
  occupied: 'bg-red-500',
  reserved: 'bg-yellow-500',
  maintenance: 'bg-gray-500',
};

export default function TablesPage() {
  const [tables, setTables] = useState<Table[]>([]);
  const [loading, setLoading] = useState(true);
  const [showForm, setShowForm] = useState(false);
  const [form, setForm] = useState({ name: '', capacity: '4', floor: 'Ground', section: '' });

  const fetchTables = async () => {
    try {
      const { data } = await api.get('/tables');
      setTables(data.tables || []);
    } catch {
      toast.error('Failed to load tables');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchTables();
    const interval = setInterval(fetchTables, 10000);
    return () => clearInterval(interval);
  }, []);

  const handleCreate = async (e: React.FormEvent) => {
    e.preventDefault();
    try {
      await api.post('/tables', { ...form, capacity: Number(form.capacity) });
      toast.success('Table created');
      setShowForm(false);
      setForm({ name: '', capacity: '4', floor: 'Ground', section: '' });
      fetchTables();
    } catch {
      toast.error('Failed to create table');
    }
  };

  const updateStatus = async (id: number, status: string) => {
    try {
      await api.patch(`/tables/${id}/status`, { status });
      fetchTables();
    } catch {
      toast.error('Failed to update');
    }
  };

  if (loading) {
    return (
      <div className="flex items-center justify-center h-64">
        <div className="w-8 h-8 border-4 border-brand border-t-transparent rounded-full animate-spin" />
      </div>
    );
  }

  return (
    <div>
      <div className="flex justify-between items-center mb-6">
        <h1 className="text-2xl font-bold text-gray-900">Tables</h1>
        <Button onClick={() => setShowForm(true)}>
          <Plus size={16} className="mr-1" /> Add Table
        </Button>
      </div>

      {/* Table Grid */}
      <div className="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4">
        {tables.map((table) => (
          <div
            key={table.id}
            className="bg-white rounded-xl p-5 border border-gray-100 text-center hover:shadow-md transition-shadow"
          >
            <div className={`w-3 h-3 rounded-full ${statusColors[table.status]} mx-auto mb-3`} />
            <h3 className="font-bold text-lg text-gray-900">{table.name}</h3>
            <p className="text-sm text-gray-500">{table.capacity} seats</p>
            <p className="text-xs text-gray-400 capitalize mt-1">{table.status}</p>
            {table.floor && <p className="text-xs text-gray-400">{table.floor}</p>}

            {table.status === 'occupied' && (
              <button
                onClick={() => updateStatus(table.id, 'available')}
                className="mt-3 text-xs text-brand hover:text-brand-hover font-medium"
              >
                Mark Available
              </button>
            )}
            {table.status === 'available' && (
              <button
                onClick={() => updateStatus(table.id, 'reserved')}
                className="mt-3 text-xs text-yellow-600 hover:text-yellow-700 font-medium"
              >
                Reserve
              </button>
            )}
          </div>
        ))}
      </div>

      {tables.length === 0 && (
        <p className="text-center text-gray-500 py-12">No tables yet. Add your first table!</p>
      )}

      {/* Add Table Modal */}
      {showForm && (
        <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
          <div className="bg-white rounded-2xl p-6 w-full max-w-sm">
            <div className="flex justify-between items-center mb-4">
              <h2 className="text-lg font-bold">Add Table</h2>
              <button onClick={() => setShowForm(false)} className="text-gray-400 hover:text-gray-600"><X size={20} /></button>
            </div>
            <form onSubmit={handleCreate} className="space-y-4">
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">Name</label>
                <input type="text" value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })}
                  placeholder="e.g., T1, Table 1" className="w-full px-3 py-2 border border-gray-300 rounded-lg outline-none focus:ring-2 focus:ring-brand" required />
              </div>
              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">Capacity</label>
                  <input type="number" min="1" value={form.capacity} onChange={(e) => setForm({ ...form, capacity: e.target.value })}
                    className="w-full px-3 py-2 border border-gray-300 rounded-lg outline-none focus:ring-2 focus:ring-brand" required />
                </div>
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">Floor</label>
                  <input type="text" value={form.floor} onChange={(e) => setForm({ ...form, floor: e.target.value })}
                    className="w-full px-3 py-2 border border-gray-300 rounded-lg outline-none focus:ring-2 focus:ring-brand" />
                </div>
              </div>
              <Button type="submit" className="w-full">Create Table</Button>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}
