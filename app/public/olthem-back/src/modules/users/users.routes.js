import { Router } from 'express';
import { z } from 'zod';
import { prisma } from '../../lib/prisma.js';
import { authRequired } from '../../middlewares/auth.js';
import { requireRole } from '../../middlewares/rbac.js';

export const usersRouter = Router();

usersRouter.use(authRequired, requireRole('admin'));

usersRouter.get('/', async (req, res, next) => {
  try {
    const users = await prisma.utilisateur.findMany({
      select: {
        id: true,
        email: true,
        displayName: true,
        role: true,
        status: true,
        createdAt: true,
      },
      orderBy: { createdAt: 'desc' },
    });

    return res.json(users);
  } catch (error) {
    return next(error);
  }
});

const patchSchema = z.object({
  role: z.enum(['admin', 'editor', 'member']).optional(),
  status: z.enum(['active', 'disabled']).optional(),
  displayName: z.string().min(2).optional(),
});

usersRouter.patch('/:id', async (req, res, next) => {
  try {
    const id = Number(req.params.id);
    const payload = patchSchema.parse(req.body);

    const updated = await prisma.utilisateur.update({
      where: { id },
      data: payload,
      select: {
        id: true,
        email: true,
        displayName: true,
        role: true,
        status: true,
      },
    });

    return res.json(updated);
  } catch (error) {
    return next(error);
  }
});
