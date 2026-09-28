<script setup lang="ts">
import BaseIcon from '@/shared/components/BaseIcon.vue';
import FieldError from '@/shared/components/FieldError.vue';
import AppShell from '@/shared/layouts/AppShell.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';

interface Option { value: number; label: string; code?: string; specialty?: string }
interface Room { id: number; location_id: number; location_name: string; name: string; code: string; sector: string | null; floor: string | null; equipment: string[]; operational_restrictions: string | null; is_active: boolean }
interface Holiday { id: number; location_id: number; location_name: string; date: string; name: string }
interface Leave { id: number; professional_name: string; location_name: string; starts_on: string; ends_on: string; reason: string }
interface Exception { id: number; professional_name: string; location_name: string; date: string; type: string; type_label: string; start_time: string | null; end_time: string | null; reason: string | null }
interface ScheduleBreak { id: number; start_time: string; end_time: string; label: string | null }
interface Schedule { id: number; professional_name: string; location_id: number; location_name: string; weekday_label: string; start_time: string; end_time: string; consulting_room_id: number | null; consulting_room_name: string | null; breaks: ScheduleBreak[] }

const props = defineProps<{
    locations: Option[];
    professionals: Option[];
    rooms: Room[];
    holidays: Holiday[];
    leaves: Leave[];
    exceptions: Exception[];
    schedules: Schedule[];
    permissions: { manage: boolean };
}>();

const defaultLocation = props.locations[0]?.value ?? '';
const defaultProfessional = props.professionals[0]?.value ?? '';

const roomForm = useForm({ location_id: defaultLocation, name: '', code: '', sector: '', floor: '', equipment_text: '', operational_restrictions: '' });
const holidayForm = useForm({ location_id: defaultLocation, date: '', name: '' });
const leaveForm = useForm({ professional_id: defaultProfessional, location_id: defaultLocation, starts_on: '', ends_on: '', reason: '' });
const exceptionForm = useForm({ professional_id: defaultProfessional, location_id: defaultLocation, date: '', type: 'unavailable', start_time: '', end_time: '', reason: '' });
const breakForms = reactive<Record<number, { start_time: string; end_time: string; label: string }>>({});

const activeRooms = computed(() => props.rooms.filter((room) => room.is_active));
const roomsForLocation = (locationId: number): Room[] => activeRooms.value.filter((room) => room.location_id === locationId);
const fieldClass = 'mt-2 min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm text-slate-950 focus:border-clinic-600 focus:ring-2 focus:ring-clinic-600/20 dark:border-slate-700 dark:bg-slate-950 dark:text-white';
const cardClass = 'rounded-2xl border border-slate-200 bg-white shadow-card dark:border-slate-800 dark:bg-slate-900';

const storeRoom = (): void => {
    roomForm.transform((data) => ({
        ...data,
        equipment: data.equipment_text.split(',').map((item) => item.trim()).filter(Boolean),
        equipment_text: undefined,
    })).post('/agenda-operativa/consultorios', { preserveScroll: true, onSuccess: () => roomForm.reset('name', 'code', 'sector', 'floor', 'equipment_text', 'operational_restrictions') });
};
const storeHoliday = (): void => holidayForm.post('/agenda-operativa/feriados', { preserveScroll: true, onSuccess: () => holidayForm.reset('date', 'name') });
const storeLeave = (): void => leaveForm.post('/agenda-operativa/licencias', { preserveScroll: true, onSuccess: () => leaveForm.reset('starts_on', 'ends_on', 'reason') });
const storeException = (): void => exceptionForm.post('/agenda-operativa/excepciones', { preserveScroll: true, onSuccess: () => exceptionForm.reset('date', 'start_time', 'end_time', 'reason') });

const breakForm = (scheduleId: number) => breakForms[scheduleId] ??= { start_time: '', end_time: '', label: '' };
const storeBreak = (scheduleId: number): void => {
    router.post(`/agenda-operativa/franjas/${scheduleId}/descansos`, breakForm(scheduleId), {
        preserveScroll: true,
        onSuccess: () => { breakForms[scheduleId] = { start_time: '', end_time: '', label: '' }; },
    });
};
const assignRoom = (schedule: Schedule, value: Event): void => {
    const target = value.target as HTMLSelectElement;
    router.patch(`/agenda-operativa/franjas/${schedule.id}/consultorio`, { consulting_room_id: target.value || null }, { preserveScroll: true });
};
const remove = (url: string, label: string): void => {
    if (window.confirm(`¿Eliminar ${label}?`)) {
        router.delete(url, { preserveScroll: true });
    }
};
const formatTime = (value: string | null): string => value?.slice(0, 5) ?? '';
</script>

<template>
    <Head title="Agenda operativa" />
    <AppShell title="Agenda operativa" eyebrow="Profesionales" description="Descansos, feriados, licencias, excepciones y asignación de consultorios por sede.">
        <div class="space-y-6">
            <div v-if="!permissions.manage" class="rounded-2xl border border-blue-200 bg-blue-50 p-4 text-sm text-blue-800 dark:border-blue-900 dark:bg-blue-950/30 dark:text-blue-200">
                La agenda está disponible en modo consulta. Sólo administradores y encargados pueden modificarla.
            </div>

            <section :class="cardClass">
                <div class="border-b border-slate-200 p-5 dark:border-slate-800 sm:p-6">
                    <h2 class="font-semibold text-slate-950 dark:text-white">Consultorios</h2>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Catálogo por sede, equipamiento y restricciones operativas.</p>
                </div>
                <form v-if="permissions.manage" class="grid gap-4 p-5 sm:grid-cols-2 lg:grid-cols-4 sm:p-6" @submit.prevent="storeRoom">
                    <label class="text-sm font-medium text-slate-700 dark:text-slate-200">Sede<select v-model="roomForm.location_id" :class="fieldClass" required><option v-for="location in locations" :key="location.value" :value="location.value">{{ location.label }}</option></select><FieldError :message="roomForm.errors.location_id" /></label>
                    <label class="text-sm font-medium text-slate-700 dark:text-slate-200">Nombre<input v-model="roomForm.name" :class="fieldClass" required placeholder="Consultorio 204" /><FieldError :message="roomForm.errors.name" /></label>
                    <label class="text-sm font-medium text-slate-700 dark:text-slate-200">Código<input v-model="roomForm.code" :class="fieldClass" required placeholder="CONS-204" /><FieldError :message="roomForm.errors.code" /></label>
                    <label class="text-sm font-medium text-slate-700 dark:text-slate-200">Sector<input v-model="roomForm.sector" :class="fieldClass" placeholder="Ambulatorio" /></label>
                    <label class="text-sm font-medium text-slate-700 dark:text-slate-200">Piso<input v-model="roomForm.floor" :class="fieldClass" placeholder="2" /></label>
                    <label class="text-sm font-medium text-slate-700 dark:text-slate-200 lg:col-span-2">Equipamiento<input v-model="roomForm.equipment_text" :class="fieldClass" placeholder="Camilla, ECG, Oxígeno" /><span class="mt-1 block text-xs text-slate-400">Separado por comas.</span></label>
                    <label class="text-sm font-medium text-slate-700 dark:text-slate-200 sm:col-span-2 lg:col-span-3">Restricciones<input v-model="roomForm.operational_restrictions" :class="fieldClass" placeholder="Uso exclusivo, accesibilidad u otra condición" /></label>
                    <button class="inline-flex min-h-11 items-center justify-center gap-2 self-end rounded-xl bg-clinic-600 px-4 text-sm font-semibold text-white hover:bg-clinic-700 disabled:opacity-60" :disabled="roomForm.processing"><BaseIcon name="plus" class="size-4" />Agregar</button>
                </form>
                <div class="grid gap-3 p-5 sm:grid-cols-2 xl:grid-cols-3 sm:p-6" :class="{ 'border-t border-slate-200 dark:border-slate-800': permissions.manage }">
                    <article v-for="room in rooms" :key="room.id" class="rounded-xl border p-4" :class="room.is_active ? 'border-slate-200 dark:border-slate-700' : 'border-slate-200 bg-slate-50 opacity-65 dark:border-slate-800 dark:bg-slate-950'">
                        <div class="flex items-start justify-between gap-3"><div><p class="font-semibold text-slate-900 dark:text-white">{{ room.name }}</p><p class="text-xs text-slate-500">{{ room.location_name }} · {{ room.code }}</p></div><button v-if="permissions.manage && room.is_active" class="rounded-lg p-2 text-slate-400 hover:bg-rose-50 hover:text-rose-600" aria-label="Desactivar consultorio" @click="remove(`/agenda-operativa/consultorios/${room.id}`, room.name)"><BaseIcon name="archive" class="size-4" /></button></div>
                        <p v-if="room.sector || room.floor" class="mt-3 text-xs text-slate-600 dark:text-slate-300">{{ room.sector }}<template v-if="room.floor"> · Piso {{ room.floor }}</template></p>
                        <p v-if="room.equipment.length" class="mt-2 text-xs text-slate-500">{{ room.equipment.join(' · ') }}</p>
                        <p v-if="room.operational_restrictions" class="mt-2 rounded-lg bg-amber-50 p-2 text-xs text-amber-800 dark:bg-amber-950/30 dark:text-amber-200">{{ room.operational_restrictions }}</p>
                    </article>
                    <p v-if="rooms.length === 0" class="text-sm text-slate-500">Todavía no hay consultorios cargados.</p>
                </div>
            </section>

            <div class="grid gap-6 xl:grid-cols-3">
                <section :class="cardClass">
                    <div class="border-b border-slate-200 p-5 dark:border-slate-800"><h2 class="font-semibold text-slate-950 dark:text-white">Feriados</h2></div>
                    <form v-if="permissions.manage" class="space-y-4 p-5" @submit.prevent="storeHoliday">
                        <select v-model="holidayForm.location_id" :class="fieldClass" required><option v-for="location in locations" :key="location.value" :value="location.value">{{ location.label }}</option></select>
                        <input v-model="holidayForm.date" type="date" :class="fieldClass" required />
                        <input v-model="holidayForm.name" :class="fieldClass" required placeholder="Nombre del feriado" />
                        <FieldError :message="holidayForm.errors.date || holidayForm.errors.name" />
                        <button class="min-h-11 w-full rounded-xl bg-clinic-600 px-4 text-sm font-semibold text-white">Agregar feriado</button>
                    </form>
                    <ul class="divide-y divide-slate-100 p-5 dark:divide-slate-800"><li v-for="holiday in holidays" :key="holiday.id" class="flex items-center justify-between gap-3 py-3"><div><p class="text-sm font-medium text-slate-800 dark:text-slate-100">{{ holiday.name }}</p><p class="text-xs text-slate-500">{{ holiday.date }} · {{ holiday.location_name }}</p></div><button v-if="permissions.manage" class="p-2 text-slate-400 hover:text-rose-600" @click="remove(`/agenda-operativa/feriados/${holiday.id}`, holiday.name)"><BaseIcon name="close" class="size-4" /></button></li></ul>
                </section>

                <section :class="cardClass">
                    <div class="border-b border-slate-200 p-5 dark:border-slate-800"><h2 class="font-semibold text-slate-950 dark:text-white">Licencias</h2></div>
                    <form v-if="permissions.manage" class="space-y-4 p-5" @submit.prevent="storeLeave">
                        <select v-model="leaveForm.professional_id" :class="fieldClass" required><option v-for="professional in professionals" :key="professional.value" :value="professional.value">{{ professional.label }}</option></select>
                        <select v-model="leaveForm.location_id" :class="fieldClass" required><option v-for="location in locations" :key="location.value" :value="location.value">{{ location.label }}</option></select>
                        <div class="grid grid-cols-2 gap-3"><input v-model="leaveForm.starts_on" type="date" :class="fieldClass" required /><input v-model="leaveForm.ends_on" type="date" :class="fieldClass" required /></div>
                        <input v-model="leaveForm.reason" :class="fieldClass" required placeholder="Motivo administrativo" />
                        <FieldError :message="leaveForm.errors.starts_on || leaveForm.errors.location_id" />
                        <button class="min-h-11 w-full rounded-xl bg-clinic-600 px-4 text-sm font-semibold text-white">Registrar licencia</button>
                    </form>
                    <ul class="divide-y divide-slate-100 p-5 dark:divide-slate-800"><li v-for="leave in leaves" :key="leave.id" class="flex items-start justify-between gap-3 py-3"><div><p class="text-sm font-medium text-slate-800 dark:text-slate-100">{{ leave.professional_name }}</p><p class="text-xs text-slate-500">{{ leave.starts_on }} → {{ leave.ends_on }} · {{ leave.location_name }}</p><p class="mt-1 text-xs text-slate-500">{{ leave.reason }}</p></div><button v-if="permissions.manage" class="p-2 text-slate-400 hover:text-rose-600" @click="remove(`/agenda-operativa/licencias/${leave.id}`, 'la licencia')"><BaseIcon name="close" class="size-4" /></button></li></ul>
                </section>

                <section :class="cardClass">
                    <div class="border-b border-slate-200 p-5 dark:border-slate-800"><h2 class="font-semibold text-slate-950 dark:text-white">Excepciones por fecha</h2></div>
                    <form v-if="permissions.manage" class="space-y-4 p-5" @submit.prevent="storeException">
                        <select v-model="exceptionForm.professional_id" :class="fieldClass" required><option v-for="professional in professionals" :key="professional.value" :value="professional.value">{{ professional.label }}</option></select>
                        <select v-model="exceptionForm.location_id" :class="fieldClass" required><option v-for="location in locations" :key="location.value" :value="location.value">{{ location.label }}</option></select>
                        <input v-model="exceptionForm.date" type="date" :class="fieldClass" required />
                        <select v-model="exceptionForm.type" :class="fieldClass"><option value="unavailable">Bloqueo completo</option><option value="available">Disponibilidad especial</option></select>
                        <div v-if="exceptionForm.type === 'available'" class="grid grid-cols-2 gap-3"><input v-model="exceptionForm.start_time" type="time" :class="fieldClass" required /><input v-model="exceptionForm.end_time" type="time" :class="fieldClass" required /></div>
                        <input v-model="exceptionForm.reason" :class="fieldClass" placeholder="Motivo" />
                        <FieldError :message="exceptionForm.errors.date || exceptionForm.errors.end_time" />
                        <button class="min-h-11 w-full rounded-xl bg-clinic-600 px-4 text-sm font-semibold text-white">Agregar excepción</button>
                    </form>
                    <ul class="divide-y divide-slate-100 p-5 dark:divide-slate-800"><li v-for="item in exceptions" :key="item.id" class="flex items-start justify-between gap-3 py-3"><div><p class="text-sm font-medium text-slate-800 dark:text-slate-100">{{ item.professional_name }}</p><p class="text-xs text-slate-500">{{ item.date }} · {{ item.location_name }}</p><p class="mt-1 text-xs font-medium text-clinic-700 dark:text-clinic-300">{{ item.type_label }}<template v-if="item.start_time"> · {{ formatTime(item.start_time) }}–{{ formatTime(item.end_time) }}</template></p></div><button v-if="permissions.manage" class="p-2 text-slate-400 hover:text-rose-600" @click="remove(`/agenda-operativa/excepciones/${item.id}`, 'la excepción')"><BaseIcon name="close" class="size-4" /></button></li></ul>
                </section>
            </div>

            <section :class="cardClass">
                <div class="border-b border-slate-200 p-5 dark:border-slate-800 sm:p-6"><h2 class="font-semibold text-slate-950 dark:text-white">Franjas semanales, descansos y consultorio fijo</h2><p class="mt-1 text-sm text-slate-500 dark:text-slate-400">La asignación fija tiene prioridad; sin ella, el turno usa asignación automática.</p></div>
                <div class="grid gap-4 p-5 lg:grid-cols-2 sm:p-6">
                    <article v-for="schedule in schedules" :key="schedule.id" class="rounded-xl border border-slate-200 p-4 dark:border-slate-700">
                        <div><p class="font-semibold text-slate-900 dark:text-white">{{ schedule.professional_name }}</p><p class="text-xs text-slate-500">{{ schedule.weekday_label }} · {{ formatTime(schedule.start_time) }}–{{ formatTime(schedule.end_time) }} · {{ schedule.location_name }}</p></div>
                        <label class="mt-4 block text-xs font-medium text-slate-600 dark:text-slate-300">Consultorio fijo<select :value="schedule.consulting_room_id ?? ''" :class="fieldClass" :disabled="!permissions.manage" @change="assignRoom(schedule, $event)"><option value="">Asignación automática</option><option v-for="room in roomsForLocation(schedule.location_id)" :key="room.id" :value="room.id">{{ room.name }} · {{ room.code }}</option></select></label>
                        <div class="mt-4 space-y-2"><div v-for="item in schedule.breaks" :key="item.id" class="flex items-center justify-between rounded-lg bg-slate-50 px-3 py-2 text-xs text-slate-600 dark:bg-slate-950 dark:text-slate-300"><span>{{ formatTime(item.start_time) }}–{{ formatTime(item.end_time) }} · {{ item.label || 'Descanso' }}</span><button v-if="permissions.manage" class="text-slate-400 hover:text-rose-600" @click="remove(`/agenda-operativa/descansos/${item.id}`, 'el descanso')"><BaseIcon name="close" class="size-4" /></button></div></div>
                        <div v-if="permissions.manage" class="mt-4 grid grid-cols-2 gap-2"><input v-model="breakForm(schedule.id).start_time" type="time" :class="fieldClass" aria-label="Inicio del descanso" /><input v-model="breakForm(schedule.id).end_time" type="time" :class="fieldClass" aria-label="Fin del descanso" /><input v-model="breakForm(schedule.id).label" :class="fieldClass" class="col-span-2" placeholder="Etiqueta opcional" /><button class="col-span-2 min-h-10 rounded-xl border border-clinic-200 text-xs font-semibold text-clinic-700 hover:bg-clinic-50 dark:border-clinic-800 dark:text-clinic-300" @click="storeBreak(schedule.id)">Agregar descanso</button></div>
                    </article>
                    <p v-if="schedules.length === 0" class="text-sm text-slate-500">No hay franjas semanales dentro de tu alcance.</p>
                </div>
            </section>
        </div>
    </AppShell>
</template>
