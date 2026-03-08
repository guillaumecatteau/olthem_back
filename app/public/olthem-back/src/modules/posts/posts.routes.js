import { Router } from 'express';
import { z } from 'zod';
import { prisma } from '../../lib/prisma.js';
import { authRequired } from '../../middlewares/auth.js';
import { requireRole } from '../../middlewares/rbac.js';

export const postsRouter = Router();
export const adminPostsRouter = Router();

postsRouter.get('/', async (req, res, next) => {
  try {
    const posts = await prisma.post.findMany({
      where: { publicationStatus: 'published' },
      orderBy: { publishedAt: 'desc' },
      select: {
        id: true,
        slug: true,
        title: true,
        excerpt: true,
        publishedAt: true,
      },
    });

    return res.json(posts);
  } catch (error) {
    return next(error);
  }
});

postsRouter.get('/:slug', async (req, res, next) => {
  try {
    const post = await prisma.post.findFirst({
      where: {
        slug: req.params.slug,
        publicationStatus: 'published',
      },
      select: {
        id: true,
        slug: true,
        title: true,
        excerpt: true,
        content: true,
        coverImage: true,
        publishedAt: true,
      },
    });

    if (!post) {
      return res.status(404).json({ error: 'Post not found' });
    }

    return res.json(post);
  } catch (error) {
    return next(error);
  }
});

const adminPostSchema = z.object({
  slug: z.string().min(3),
  title: z.string().min(3),
  excerpt: z.string().default(''),
  content: z.string().default(''),
  coverImage: z.string().url().optional().nullable(),
  publicationStatus: z.enum(['draft', 'published']).default('draft'),
});

adminPostsRouter.post('/', authRequired, requireRole('admin'), async (req, res, next) => {
  try {
    const payload = adminPostSchema.parse(req.body);
    const data = {
      ...payload,
      authorId: req.user.id,
      publishedAt: payload.publicationStatus === 'published' ? new Date() : null,
    };

    const created = await prisma.post.create({ data });
    return res.status(201).json(created);
  } catch (error) {
    return next(error);
  }
});

adminPostsRouter.patch('/:id', authRequired, requireRole('admin'), async (req, res, next) => {
  try {
    const payload = adminPostSchema.partial().parse(req.body);
    const id = Number(req.params.id);

    const data = {
      ...payload,
    };

    if (payload.publicationStatus === 'published') {
      data.publishedAt = new Date();
    }

    const updated = await prisma.post.update({
      where: { id },
      data,
    });

    return res.json(updated);
  } catch (error) {
    return next(error);
  }
});

adminPostsRouter.delete('/:id', authRequired, requireRole('admin'), async (req, res, next) => {
  try {
    const id = Number(req.params.id);
    await prisma.post.delete({ where: { id } });
    return res.json({ ok: true });
  } catch (error) {
    return next(error);
  }
});
