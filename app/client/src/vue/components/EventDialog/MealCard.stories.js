import MealCard from './MealCard.vue'

export default {
  title: 'Design System/MealCard',
  component: MealCard,
  tags: ['autodocs'],
  argTypes: {
    preference: { control: 'select', options: ['None', 'Vegetarian', 'Vegan'] },
  },
  args: {
    title: 'Pasta Bolognese',
    supplier: 'Pizzeria Roma',
    preference: 'None',
  },
}

export const Basic = {}

export const Vegetarian = {
  args: { preference: 'Vegetarian' },
}

export const Vegan = {
  args: { title: 'Gemüsecurry', supplier: 'Streetfood Truck', preference: 'Vegan' },
}

export const Orderable = {
  args: {
    orderable: true,
    canOrder: true,
    quantity: 2,
    maxQuantity: 10,
  },
}

export const OrderableAtMax = {
  args: {
    orderable: true,
    canOrder: true,
    quantity: 5,
    maxQuantity: 5,
  },
}

export const OrderableButCannotOrder = {
  args: {
    orderable: true,
    canOrder: false,
    quantity: 0,
    maxQuantity: 10,
  },
}
