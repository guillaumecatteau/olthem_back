import 'dotenv/config';
import bcrypt from 'bcryptjs';
import { PrismaClient } from '@prisma/client';

const prisma = new PrismaClient();

async function main() {
  const email = process.env.ADMIN_SEED_EMAIL || 'admin@olthem.local';
  const password = process.env.ADMIN_SEED_PASSWORD || 'ChangeMe123!';
  const passwordHash = await bcrypt.hash(password, 12);

  const admin = await prisma.utilisateur.upsert({
    where: { email },
    update: {
      displayName: 'Admin Olthem',
      role: 'admin',
      status: 'active',
      passwordHash,
    },
    create: {
      email,
      displayName: 'Admin Olthem',
      passwordHash,
      role: 'admin',
      status: 'active',
    },
  });

  await prisma.post.upsert({
    where: { slug: 'premier-article-olthem' },
    update: {},
    create: {
      slug: 'premier-article-olthem',
      title: 'Premier article Olthem',
      excerpt: 'Exemple de post pour tester l overlay blog.',
      content: 'Contenu de test. Ce post permet de verifier que /blog et /blog/:slug repondent.',
      publicationStatus: 'published',
      publishedAt: new Date(),
      authorId: admin.id,
    },
  });

  await prisma.atelier.upsert({
    where: { slug: 'atelier-intro-olthem' },
    update: {},
    create: {
      slug: 'atelier-intro-olthem',
      title: 'Atelier Intro Olthem',
      description: 'Atelier de demonstration pour la section ateliers.',
      place: 'En ligne',
      capacity: 20,
      startDate: new Date(),
      publicationStatus: 'published',
      createdById: admin.id,
    },
  });

  console.log('Seed complete. Admin:', email);
}

main()
  .catch((error) => {
    console.error(error);
    process.exit(1);
  })
  .finally(async () => {
    await prisma.$disconnect();
  });
