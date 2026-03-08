import { Router } from 'express';
import { z } from 'zod';
import { prisma } from '../../lib/prisma.js';
import { authRequired } from '../../middlewares/auth.js';
import { requireRole } from '../../middlewares/rbac.js';

export const ateliersRouter = Router();
export const adminAteliersRouter = Router();

ateliersRouter.get('/', async (req, res, next) => {
  try {
    const ateliers = await prisma.atelier.findMany({
      where: { publicationStatus: 'published' },
      orderBy: { startDate: 'asc' },
    });

    return res.json(ateliers);
  } catch (error) {
    return next(error);
  }
});

ateliersRouter.get('/:slug', async (req, res, next) => {
  try {
    const atelier = await prisma.atelier.findFirst({
      where: {
        slug: req.params.slug,
        publicationStatus: 'published',
      },
    });

    if (!atelier) {
      return res.status(404).json({ error: 'Atelier not found' });
    }

    return res.json(atelier);
  } catch (error) {
    return next(error);
  }
});

const atelierSchema = z.object({
  slug: z.string().min(3),
  title: z.string().min(3),
  description: z.string().default(''),
  place: z.string().default(''),
  capacity: z.number().int().positive().optional().nullable(),
  startDate: z.coerce.date(),
  endDate: z.coerce.date().optional().nullable(),
  publicationStatus: z.enum(['draft', 'published']).default('draft'),
});

adminAteliersRouter.post('/', authRequired, requireRole('admin'), async (req, res, next) => {
  try {
    const payload = atelierSchema.parse(req.body);
    const created = await prisma.atelier.create({
      data: {
        ...payload,
        createdById: req.user.id,
      },
    });

    return res.status(201).json(created);
  } catch (error) {
    return next(error);
  }
});

adminAteliersRouter.patch('/:id', authRequired, requireRole('admin'), async (req, res, next) => {
  try {
    const payload = atelierSchema.partial().parse(req.body);
    const id = Number(req.params.id);

    const updated = await prisma.atelier.update({
      where: { id },
      data: payload,
    });

    return res.json(updated);
  } catch (error) {
    return next(error);
  }
});

adminAteliersRouter.delete('/:id', authRequired, requireRole('admin'), async (req, res, next) => {
  try {
    const id = Number(req.params.id);
    await prisma.atelier.delete({ where: { id } });
    return res.json({ ok: true });
  } catch (error) {
    return next(error);
  }
});
