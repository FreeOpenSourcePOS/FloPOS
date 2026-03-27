const express = require('express');
const { Pool } = require('pg');
const bcrypt = require('bcryptjs');
const jwt = require('jsonwebtoken');

const router = express.Router();

const pool = new Pool({
  host: 'localhost',
  port: 5432,
  database: 'flopos_main',
  user: 'flopos_user',
  password: process.env.DB_PASSWORD || 'flopos_password',
});

const JWT_SECRET = process.env.JWT_SECRET || 'flopos-admin-secret-key-2024';

// Auth middleware
function authMiddleware(req, res, next) {
  const authHeader = req.headers.authorization;
  if (!authHeader || !authHeader.startsWith('Bearer ')) {
    return res.status(401).json({ error: 'No token provided' });
  }
  try {
    const token = authHeader.split(' ')[1];
    const decoded = jwt.verify(token, JWT_SECRET);
    req.adminUser = decoded;
    next();
  } catch (e) {
    return res.status(401).json({ error: 'Invalid token' });
  }
}

// Admin Login
router.post('/auth/login', async (req, res) => {
  try {
    const { email, password } = req.body;
    const result = await pool.query('SELECT * FROM admin_users WHERE email = $1 AND is_active = true', [email]);
    const user = result.rows[0];

    if (!user || !bcrypt.compareSync(password, user.password)) {
      return res.status(401).json({ error: 'Invalid credentials' });
    }

    const token = jwt.sign({ id: user.id, email: user.email, role: 'admin' }, JWT_SECRET, { expiresIn: '24h' });
    res.json({ token, user: { id: user.id, name: user.name, email: user.email } });
  } catch (e) {
    console.error('Login error:', e);
    res.status(500).json({ error: 'Login failed' });
  }
});

// Get current admin
router.get('/auth/me', authMiddleware, async (req, res) => {
  try {
    const result = await pool.query('SELECT id, name, email FROM admin_users WHERE id = $1', [req.adminUser.id]);
    if (result.rows.length === 0) return res.status(404).json({ error: 'User not found' });
    res.json(result.rows[0]);
  } catch (e) {
    res.status(500).json({ error: 'Failed to fetch user' });
  }
});

// List tenants with subscription info
router.get('/tenants', async (req, res) => {
  try {
    const { search, status, plan, page = 1, limit = 20 } = req.query;
    const offset = (page - 1) * limit;

    let whereClause = 'WHERE t.deleted_at IS NULL';
    const params = [];
    let paramIndex = 1;

    if (search) {
      whereClause += ` AND (t.business_name ILIKE $${paramIndex} OR t.slug ILIKE $${paramIndex})`;
      params.push(`%${search}%`);
      paramIndex++;
    }
    if (status) {
      whereClause += ` AND t.status = $${paramIndex}`;
      params.push(status);
      paramIndex++;
    }
    if (plan) {
      whereClause += ` AND t.plan = $${paramIndex}`;
      params.push(plan);
      paramIndex++;
    }

    const countResult = await pool.query(`SELECT COUNT(*) FROM tenants t ${whereClause}`, params);
    const total = parseInt(countResult.rows[0].count);

    params.push(limit, offset);
    const result = await pool.query(`
      SELECT 
        t.id, t.business_name, t.slug, t.business_type, t.country, t.plan, t.status, t.trial_ends_at, t.created_at,
        u.id as owner_id, u.name as owner_name, u.email as owner_email,
        s.id as subscription_id, s.plan as subscription_plan, s.status as subscription_status, 
        s.amount as subscription_amount, s.current_period_end,
        sub.support_calls_used
      FROM tenants t
      LEFT JOIN users u ON t.owner_id = u.id
      LEFT JOIN subscriptions s ON t.id = s.tenant_id AND s.status = 'active'
      LEFT JOIN LATERAL (
        SELECT COUNT(*) as support_calls_used 
        FROM support_calls sc 
        WHERE sc.merchant_id = t.id::text
      ) sub ON true
      ${whereClause}
      ORDER BY t.created_at DESC
      LIMIT $${paramIndex} OFFSET $${paramIndex + 1}
    `, params);

    res.json({
      tenants: result.rows,
      meta: {
        total,
        per_page: parseInt(limit),
        current_page: parseInt(page),
        last_page: Math.ceil(total / limit)
      }
    });
  } catch (e) {
    console.error('Tenants error:', e);
    res.status(500).json({ error: 'Failed to fetch tenants' });
  }
});

// Get single tenant
router.get('/tenants/:id', async (req, res) => {
  try {
    const { id } = req.params;
    const result = await pool.query(`
      SELECT 
        t.*,
        u.id as owner_id, u.name as owner_name, u.email as owner_email, u.phone as owner_phone,
        s.id as subscription_id, s.plan, s.status, s.amount, s.current_period_start, s.current_period_end
      FROM tenants t
      LEFT JOIN users u ON t.owner_id = u.id
      LEFT JOIN subscriptions s ON t.id = s.tenant_id AND s.status = 'active'
      WHERE t.id = $1
    `, [id]);

    if (result.rows.length === 0) return res.status(404).json({ error: 'Tenant not found' });
    res.json(result.rows[0]);
  } catch (e) {
    res.status(500).json({ error: 'Failed to fetch tenant' });
  }
});

// Update tenant subscription
router.patch('/tenants/:id/subscription', authMiddleware, async (req, res) => {
  try {
    const { id } = req.params;
    const { plan_type, status } = req.body;

    // Update tenant plan
    await pool.query('UPDATE tenants SET plan = $1, updated_at = NOW() WHERE id = $2', [plan_type, id]);

    // Update or create subscription
    if (status) {
      await pool.query(`
        INSERT INTO subscriptions (tenant_id, plan, status, updated_at)
        VALUES ($1, $2, $3, NOW())
        ON CONFLICT (tenant_id) WHERE status = 'active'
        DO UPDATE SET plan = $2, status = $3, updated_at = NOW()
      `, [id, plan_type, status]);
    }

    res.json({ success: true });
  } catch (e) {
    console.error('Subscription update error:', e);
    res.status(500).json({ error: 'Failed to update subscription' });
  }
});

// Support calls - list
router.get('/support-calls', async (req, res) => {
  try {
    const { search, status, issue_type, page = 1, limit = 20 } = req.query;
    const offset = (page - 1) * limit;

    let whereClause = '';
    const params = [];
    let paramIndex = 1;

    if (search) {
      whereClause += ` AND (t.business_name ILIKE $${paramIndex} OR sc.subject ILIKE $${paramIndex})`;
      params.push(`%${search}%`);
      paramIndex++;
    }
    if (status) {
      whereClause += ` AND sc.status = $${paramIndex}`;
      params.push(status);
      paramIndex++;
    }
    if (issue_type) {
      whereClause += ` AND sc.issue_type = $${paramIndex}`;
      params.push(issue_type);
      paramIndex++;
    }

    const countResult = await pool.query(`
      SELECT COUNT(*) FROM support_calls sc
      JOIN tenants t ON sc.merchant_id = t.id::text
      WHERE 1=1 ${whereClause}
    `, params);
    const total = parseInt(countResult.rows[0].count);

    params.push(limit, offset);
    const result = await pool.query(`
      SELECT 
        sc.*,
        t.business_name as merchant_name,
        u.name as handler_name
      FROM support_calls sc
      JOIN tenants t ON sc.merchant_id = t.id::text
      LEFT JOIN admin_users u ON sc.handled_by = u.id
      WHERE 1=1 ${whereClause}
      ORDER BY sc.called_at DESC
      LIMIT $${paramIndex} OFFSET $${paramIndex + 1}
    `, params);

    res.json({
      calls: result.rows,
      meta: { total, per_page: parseInt(limit), current_page: parseInt(page) }
    });
  } catch (e) {
    console.error('Support calls error:', e);
    res.status(500).json({ error: 'Failed to fetch support calls' });
  }
});

// Create support call
router.post('/support-calls', authMiddleware, async (req, res) => {
  try {
    const { merchant_id, issue_type, subject, description, call_type, duration_minutes } = req.body;

    const id = 'sc-' + Date.now();
    const result = await pool.query(`
      INSERT INTO support_calls (id, merchant_id, issue_type, subject, description, call_type, duration_minutes, status, called_at)
      VALUES ($1, $2, $3, $4, $5, $6, $7, 'open', NOW())
      RETURNING *
    `, [id, merchant_id, issue_type, subject, description || '', call_type || 'incoming', duration_minutes || null]);

    res.json(result.rows[0]);
  } catch (e) {
    console.error('Create support call error:', e);
    res.status(500).json({ error: 'Failed to create support call' });
  }
});

// Update support call
router.patch('/support-calls/:id', authMiddleware, async (req, res) => {
  try {
    const { id } = req.params;
    const { status, resolution, handled_by } = req.body;

    const updates = [];
    const params = [];
    let paramIndex = 1;

    if (status) { updates.push(`status = $${paramIndex}`); params.push(status); paramIndex++; }
    if (resolution !== undefined) { updates.push(`resolution = $${paramIndex}`); params.push(resolution); paramIndex++; }
    if (handled_by) { updates.push(`handled_by = $${paramIndex}`); params.push(handled_by); paramIndex++; }

    params.push(id);
    const result = await pool.query(`
      UPDATE support_calls SET ${updates.join(', ')} WHERE id = $${paramIndex} RETURNING *
    `, params);

    if (result.rows.length === 0) return res.status(404).json({ error: 'Support call not found' });
    res.json(result.rows[0]);
  } catch (e) {
    res.status(500).json({ error: 'Failed to update support call' });
  }
});

// Account managers
router.get('/account-managers', async (req, res) => {
  try {
    const { search } = req.query;
    let whereClause = 'WHERE 1=1';
    const params = [];

    if (search) {
      whereClause += ' AND (name ILIKE $1 OR email ILIKE $1)';
      params.push(`%${search}%`);
    }

    const result = await pool.query(`
      SELECT 
        am.*,
        COUNT(DISTINCT t.id) as merchants_count,
        COUNT(DISTINCT CASE WHEN sc.status = 'open' THEN sc.id END) as open_calls
      FROM account_managers am
      LEFT JOIN merchant_account_mapping m ON am.id = m.account_manager_id
      LEFT JOIN tenants t ON m.merchant_id = t.id::text
      LEFT JOIN support_calls sc ON t.id::text = sc.merchant_id
      ${whereClause}
      GROUP BY am.id
      ORDER BY am.name
    `, params);

    res.json({ account_managers: result.rows });
  } catch (e) {
    console.error('Account managers error:', e);
    res.status(500).json({ error: 'Failed to fetch account managers' });
  }
});

router.post('/account-managers', authMiddleware, async (req, res) => {
  try {
    const { name, email, phone, role } = req.body;
    const id = 'am-' + Date.now();
    const hashedPassword = bcrypt.hashSync('changeme123', 10);

    const result = await pool.query(`
      INSERT INTO account_managers (id, name, email, phone, role, password)
      VALUES ($1, $2, $3, $4, $5, $6)
      RETURNING *
    `, [id, name, email, phone, role || 'support', hashedPassword]);

    res.json(result.rows[0]);
  } catch (e) {
    console.error('Create account manager error:', e);
    res.status(500).json({ error: 'Failed to create account manager' });
  }
});

// Resellers
router.get('/resellers', async (req, res) => {
  try {
    const { search } = req.query;
    let whereClause = 'WHERE 1=1';
    const params = [];

    if (search) {
      whereClause += ' AND (company_name ILIKE $1 OR email ILIKE $1)';
      params.push(`%${search}%`);
    }

    const result = await pool.query(`
      SELECT 
        r.*,
        COUNT(DISTINCT m.id) as merchants_count,
        COALESCE(SUM(s.amount * r.commission_percent / 100), 0) as total_revenue
      FROM resellers r
      LEFT JOIN merchant_account_mapping m ON r.id = m.reseller_id
      LEFT JOIN tenants t ON m.merchant_id = t.id::text
      LEFT JOIN subscriptions s ON t.id = s.tenant_id AND s.status = 'active'
      ${whereClause}
      GROUP BY r.id
      ORDER BY r.company_name
    `, params);

    res.json({ resellers: result.rows });
  } catch (e) {
    console.error('Resellers error:', e);
    res.status(500).json({ error: 'Failed to fetch resellers' });
  }
});

router.post('/resellers', authMiddleware, async (req, res) => {
  try {
    const { company_name, contact_name, email, phone, commission_percent } = req.body;
    const id = 'res-' + Date.now();

    const result = await pool.query(`
      INSERT INTO resellers (id, company_name, contact_name, email, phone, commission_percent)
      VALUES ($1, $2, $3, $4, $5, $6)
      RETURNING *
    `, [id, company_name, contact_name, email, phone, commission_percent || 10]);

    res.json(result.rows[0]);
  } catch (e) {
    console.error('Create reseller error:', e);
    res.status(500).json({ error: 'Failed to create reseller' });
  }
});

module.exports = router;