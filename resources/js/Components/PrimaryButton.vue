<script setup>
import Button from 'primevue/button';
</script>

<template>
    <!-- type=submit: primary buttons drive the form they sit in -->
    <Button type="submit" size="small">
        <slot />
    </Button>
</template>
